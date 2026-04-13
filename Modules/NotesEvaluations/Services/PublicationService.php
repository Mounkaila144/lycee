<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\NotesEvaluations\Entities\PublicationRecord;
use Modules\NotesEvaluations\Entities\SemesterResult;
use Modules\NotesEvaluations\Events\ResultsPublished;
use Modules\NotesEvaluations\Jobs\SendResultNotificationsJob;

class PublicationService
{
    /**
     * Publish results for a semester
     */
    public function publishSemesterResults(
        int $semesterId,
        string $publicationType = 'final',
        ?int $programmeId = null,
        ?string $level = null,
        array $options = []
    ): PublicationRecord {
        $query = SemesterResult::where('semester_id', $semesterId)
            ->where('is_final', true)
            ->whereNull('published_at');

        // Filter by programme if specified
        if ($programmeId) {
            $query->whereHas('student.enrollments', function ($q) use ($programmeId) {
                $q->where('programme_id', $programmeId);
            });
        }

        // Filter by level if specified
        if ($level) {
            $query->whereHas('student.enrollments', function ($q) use ($level) {
                $q->where('level', $level);
            });
        }

        $results = $query->get();

        if ($results->isEmpty()) {
            throw new \Exception('Aucun résultat à publier.');
        }

        DB::beginTransaction();
        try {
            // Update all results
            $publishedAt = now();
            SemesterResult::whereIn('id', $results->pluck('id'))
                ->update(['published_at' => $publishedAt]);

            // Calculate statistics
            $statistics = $this->calculatePublicationStatistics($results);

            // Determine scope
            $scope = 'semester';
            if ($programmeId) {
                $scope = 'programme';
            } elseif ($level) {
                $scope = 'level';
            }

            // Create publication record
            $record = PublicationRecord::create([
                'semester_id' => $semesterId,
                'programme_id' => $programmeId,
                'publication_type' => $publicationType,
                'scope' => $scope,
                'level' => $level,
                'published_at' => $publishedAt,
                'published_by' => Auth::id(),
                'students_count' => $results->count(),
                'success_count' => $results->where('is_validated', true)->count(),
                'success_rate' => $statistics['success_rate'],
                'statistics' => $statistics,
                'notes' => $options['notes'] ?? null,
            ]);

            DB::commit();

            // Fire event
            event(new ResultsPublished($record));

            // Send notifications if requested
            if ($options['send_notifications'] ?? false) {
                $this->scheduleNotifications($record, $results);
            }

            Log::info('Results published', [
                'record_id' => $record->id,
                'semester_id' => $semesterId,
                'count' => $results->count(),
            ]);

            return $record;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calculate publication statistics
     */
    private function calculatePublicationStatistics(Collection $results): array
    {
        $total = $results->count();
        $validated = $results->where('is_validated', true)->count();

        $averages = $results->pluck('average')->filter();

        return [
            'total_students' => $total,
            'validated_count' => $validated,
            'failed_count' => $total - $validated,
            'success_rate' => $total > 0 ? round(($validated / $total) * 100, 2) : 0,
            'average' => $averages->isNotEmpty() ? round($averages->avg(), 2) : null,
            'min_average' => $averages->isNotEmpty() ? round($averages->min(), 2) : null,
            'max_average' => $averages->isNotEmpty() ? round($averages->max(), 2) : null,
            'by_global_status' => [
                'validated' => $results->where('global_status', 'validated')->count(),
                'partially_validated' => $results->where('global_status', 'partially_validated')->count(),
                'to_retake' => $results->where('global_status', 'to_retake')->count(),
                'deferred' => $results->where('global_status', 'deferred')->count(),
            ],
            'by_mention' => $this->countByMention($results),
        ];
    }

    /**
     * Count results by mention
     */
    private function countByMention(Collection $results): array
    {
        return [
            'tres_bien' => $results->filter(fn ($r) => $r->average >= 16)->count(),
            'bien' => $results->filter(fn ($r) => $r->average >= 14 && $r->average < 16)->count(),
            'assez_bien' => $results->filter(fn ($r) => $r->average >= 12 && $r->average < 14)->count(),
            'passable' => $results->filter(fn ($r) => $r->average >= 10 && $r->average < 12)->count(),
            'insuffisant' => $results->filter(fn ($r) => $r->average < 10)->count(),
        ];
    }

    /**
     * Schedule notifications to be sent
     */
    private function scheduleNotifications(PublicationRecord $record, Collection $results): void
    {
        // Dispatch job for async notification sending
        SendResultNotificationsJob::dispatch($record->id, $results->pluck('student_id')->toArray());

        $record->update([
            'notifications_sent' => true,
            'notifications_count' => $results->count(),
        ]);
    }

    /**
     * Get publication history for a semester
     */
    public function getPublicationHistory(int $semesterId): Collection
    {
        return PublicationRecord::where('semester_id', $semesterId)
            ->with(['programme', 'publishedByUser'])
            ->orderBy('published_at', 'desc')
            ->get();
    }

    /**
     * Get publication status for a semester
     */
    public function getPublicationStatus(int $semesterId): array
    {
        $total = SemesterResult::where('semester_id', $semesterId)->count();
        $final = SemesterResult::where('semester_id', $semesterId)
            ->where('is_final', true)
            ->count();
        $published = SemesterResult::where('semester_id', $semesterId)
            ->whereNotNull('published_at')
            ->count();

        return [
            'semester_id' => $semesterId,
            'total_results' => $total,
            'final_results' => $final,
            'published_results' => $published,
            'unpublished_results' => $final - $published,
            'provisional_results' => $total - $final,
            'is_fully_published' => $published === $final && $final > 0,
            'publication_percentage' => $final > 0 ? round(($published / $final) * 100, 2) : 0,
        ];
    }

    /**
     * Unpublish results (revert publication)
     */
    public function unpublishResults(PublicationRecord $record): bool
    {
        if ($record->publication_type === 'final') {
            throw new \Exception('Les résultats définitifs ne peuvent pas être dépubliés.');
        }

        DB::beginTransaction();
        try {
            // Get affected results
            $query = SemesterResult::where('semester_id', $record->semester_id)
                ->where('published_at', $record->published_at);

            if ($record->programme_id) {
                $query->whereHas('student.enrollments', function ($q) use ($record) {
                    $q->where('programme_id', $record->programme_id);
                });
            }

            // Reset publication status
            $query->update(['published_at' => null]);

            // Soft delete the record
            $record->delete();

            DB::commit();

            Log::info('Results unpublished', [
                'record_id' => $record->id,
                'semester_id' => $record->semester_id,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Check if results can be published
     */
    public function canPublish(int $semesterId, ?int $programmeId = null): array
    {
        $query = SemesterResult::where('semester_id', $semesterId);

        if ($programmeId) {
            $query->whereHas('student.enrollments', function ($q) use ($programmeId) {
                $q->where('programme_id', $programmeId);
            });
        }

        $total = (clone $query)->count();
        $final = (clone $query)->where('is_final', true)->count();
        $unpublished = (clone $query)->where('is_final', true)->whereNull('published_at')->count();

        $canPublish = $unpublished > 0;
        $reasons = [];

        if ($total === 0) {
            $canPublish = false;
            $reasons[] = 'Aucun résultat trouvé pour ce semestre.';
        }

        if ($final === 0) {
            $canPublish = false;
            $reasons[] = 'Aucun résultat final disponible.';
        }

        if ($unpublished === 0 && $final > 0) {
            $canPublish = false;
            $reasons[] = 'Tous les résultats sont déjà publiés.';
        }

        return [
            'can_publish' => $canPublish,
            'reasons' => $reasons,
            'total_results' => $total,
            'final_results' => $final,
            'unpublished_count' => $unpublished,
        ];
    }

    /**
     * Get student published results
     */
    public function getStudentPublishedResults(int $studentId): Collection
    {
        return SemesterResult::where('student_id', $studentId)
            ->whereNotNull('published_at')
            ->with(['semester.academicYear'])
            ->orderBy('published_at', 'desc')
            ->get();
    }

    /**
     * Generate publication summary for export
     */
    public function generatePublicationSummary(PublicationRecord $record): array
    {
        $results = SemesterResult::where('semester_id', $record->semester_id)
            ->where('published_at', $record->published_at)
            ->with(['student', 'semester'])
            ->orderBy('rank')
            ->get();

        return [
            'publication' => [
                'id' => $record->id,
                'type' => $record->publication_type_label,
                'scope' => $record->scope_label,
                'published_at' => $record->published_at->format('d/m/Y H:i'),
                'published_by' => $record->publishedByUser?->name,
            ],
            'statistics' => $record->statistics,
            'results' => $results->map(fn ($r) => [
                'rank' => $r->rank,
                'matricule' => $r->student->matricule,
                'full_name' => $r->student->full_name ?? $r->student->firstname.' '.$r->student->lastname,
                'average' => $r->average,
                'mention' => $r->mention,
                'global_status' => $r->global_status_label,
                'validated_modules' => $r->validated_modules_count,
                'compensated_modules' => $r->compensated_modules_count,
                'failed_modules' => $r->failed_modules_count,
                'acquired_credits' => $r->acquired_credits,
                'total_credits' => $r->total_credits,
            ])->toArray(),
        ];
    }
}
