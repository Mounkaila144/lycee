<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\GradeConfig;
use Modules\NotesEvaluations\Entities\JuryDecision;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Entities\SemesterResult;
use Modules\NotesEvaluations\Jobs\GenerateFinalDocumentsJob;
use Modules\NotesEvaluations\Notifications\FinalResultsNotification;
use Modules\StructureAcademique\Entities\Semester;

class FinalResultsService
{
    /**
     * Determine the final status for a student in a semester
     */
    public function determineFinalStatus(int $studentId, int $semesterId): string
    {
        $result = SemesterResult::where([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
        ])->first();

        if (! $result) {
            return 'deferred_final';
        }

        $config = GradeConfig::getConfig();
        $progressionThreshold = $config->year_progression_threshold ?? 80;

        // Calculate credits percentage
        $creditsPercentage = $result->total_credits > 0
            ? ($result->acquired_credits / $result->total_credits) * 100
            : 0;

        // Check if jury decided repeating
        $juryDecision = JuryDecision::where([
            'student_id' => $studentId,
            'semester_result_id' => $result->id,
        ])->first();

        if ($juryDecision && $juryDecision->decision_type === 'repeating') {
            return 'repeating';
        }

        // Fully admitted: all credits acquired and average >= 10
        if ($result->acquired_credits === $result->total_credits && $result->average >= 10) {
            return 'admitted';
        }

        // Admitted with debts: threshold reached but not all credits
        if ($creditsPercentage >= $progressionThreshold && $result->average >= 10) {
            return 'admitted_with_debts';
        }

        // Check for failed eliminatory modules
        if ($result->validation_blocked_by_eliminatory) {
            return 'deferred_final';
        }

        // Deferred final: insufficient credits
        return 'deferred_final';
    }

    /**
     * Check prerequisites for publishing final results
     */
    public function canPublishFinalResults(int $semesterId): array
    {
        $semester = Semester::find($semesterId);
        $issues = [];

        // Check all retake grades are published
        $unpublishedRetakes = DB::connection('tenant')
            ->table('retake_grades')
            ->join('retake_enrollments', 'retake_grades.retake_enrollment_id', '=', 'retake_enrollments.id')
            ->where('retake_enrollments.semester_id', $semesterId)
            ->where('retake_grades.status', '!=', 'published')
            ->count();

        if ($unpublishedRetakes > 0) {
            $issues[] = "Il reste {$unpublishedRetakes} note(s) de rattrapage non publiée(s).";
        }

        // Check recalculations completed
        $pendingRecalculations = SemesterResult::where('semester_id', $semesterId)
            ->where('retake_session_completed', false)
            ->whereHas('student.retakeEnrollments', fn ($q) => $q->where('semester_id', $semesterId))
            ->count();

        if ($pendingRecalculations > 0) {
            $issues[] = "Il reste {$pendingRecalculations} résultat(s) en attente de recalcul.";
        }

        // Check results exist
        $resultsCount = SemesterResult::where('semester_id', $semesterId)->count();

        if ($resultsCount === 0) {
            $issues[] = 'Aucun résultat de semestre trouvé.';
        }

        return [
            'can_publish' => empty($issues),
            'issues' => $issues,
            'results_count' => $resultsCount,
            'semester' => $semester ? [
                'id' => $semester->id,
                'name' => $semester->name,
            ] : null,
        ];
    }

    /**
     * Publish final results for a semester
     */
    public function publishFinalResults(
        int $semesterId,
        bool $notifyStudents = true,
        bool $generateAttestations = true
    ): array {
        $results = SemesterResult::where('semester_id', $semesterId)
            ->with(['student', 'semester'])
            ->get();

        $stats = [
            'admitted' => 0,
            'admitted_with_debts' => 0,
            'deferred_final' => 0,
            'repeating' => 0,
            'total' => $results->count(),
        ];

        DB::beginTransaction();
        try {
            foreach ($results as $result) {
                $finalStatus = $this->determineFinalStatus($result->student_id, $semesterId);

                $canProgress = in_array($finalStatus, ['admitted', 'admitted_with_debts']);

                $result->update([
                    'final_status' => $finalStatus,
                    'can_progress_next_year' => $canProgress,
                    'final_published_at' => now(),
                ]);

                $stats[$finalStatus]++;

                // Generate attestation if admitted
                if ($generateAttestations && $finalStatus === 'admitted') {
                    GenerateFinalDocumentsJob::dispatch($result);
                }

                // Notify student
                if ($notifyStudents && $result->student) {
                    $result->student->notify(new FinalResultsNotification($result));
                }

                // Update student can_register_next_year flag
                if ($canProgress && $result->student_id) {
                    Student::where('id', $result->student_id)
                        ->update(['can_register_next_year' => true]);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Résultats finaux publiés avec succès.',
                'statistics' => $stats,
                'notifications_sent' => $notifyStudents,
                'attestations_generated' => $generateAttestations ? $stats['admitted'] : 0,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get final statistics for a semester
     */
    public function getFinalStatistics(int $semesterId): array
    {
        $results = SemesterResult::where('semester_id', $semesterId)->get();
        $total = $results->count();

        if ($total === 0) {
            return [
                'total' => 0,
                'published' => false,
                'statistics' => [],
            ];
        }

        // Status distribution
        $statusCounts = $results->groupBy('final_status')->map->count();

        // Calculate rates
        $admitted = $statusCounts->get('admitted', 0);
        $admittedWithDebts = $statusCounts->get('admitted_with_debts', 0);
        $deferredFinal = $statusCounts->get('deferred_final', 0);
        $repeating = $statusCounts->get('repeating', 0);
        $inProgress = $statusCounts->get('in_progress', 0);

        $successRate = $total > 0 ? round((($admitted + $admittedWithDebts) / $total) * 100, 2) : 0;

        // Average calculations
        $averageGlobal = $results->whereNotNull('average')->avg('average');
        $averageAdmitted = $results->where('final_status', 'admitted')->whereNotNull('average')->avg('average');

        // ECTS distribution
        $creditsDistribution = $results->groupBy(function ($r) {
            if ($r->acquired_credits === $r->total_credits) {
                return 'full';
            }
            if ($r->acquired_credits >= $r->total_credits * 0.8) {
                return 'high';
            }
            if ($r->acquired_credits >= $r->total_credits * 0.5) {
                return 'medium';
            }

            return 'low';
        })->map->count();

        // Impact of retake session
        $resultsWithRetake = $results->filter(function ($r) {
            return $r->retake_session_completed;
        });

        $improvedByRetake = $resultsWithRetake->filter(function ($r) {
            return in_array($r->final_status, ['admitted', 'admitted_with_debts']);
        })->count();

        return [
            'total' => $total,
            'published' => $results->whereNotNull('final_published_at')->count() > 0,
            'published_at' => $results->whereNotNull('final_published_at')->first()?->final_published_at,
            'statistics' => [
                'by_status' => [
                    'admitted' => $admitted,
                    'admitted_with_debts' => $admittedWithDebts,
                    'deferred_final' => $deferredFinal,
                    'repeating' => $repeating,
                    'in_progress' => $inProgress,
                ],
                'rates' => [
                    'success_rate' => $successRate,
                    'admission_rate' => $total > 0 ? round(($admitted / $total) * 100, 2) : 0,
                    'failure_rate' => $total > 0 ? round((($deferredFinal + $repeating) / $total) * 100, 2) : 0,
                ],
                'averages' => [
                    'global' => $averageGlobal ? round($averageGlobal, 2) : null,
                    'admitted_students' => $averageAdmitted ? round($averageAdmitted, 2) : null,
                ],
                'credits_distribution' => [
                    'full' => $creditsDistribution->get('full', 0),
                    'high' => $creditsDistribution->get('high', 0),
                    'medium' => $creditsDistribution->get('medium', 0),
                    'low' => $creditsDistribution->get('low', 0),
                ],
                'retake_impact' => [
                    'students_with_retake' => $resultsWithRetake->count(),
                    'improved_by_retake' => $improvedByRetake,
                ],
            ],
        ];
    }

    /**
     * Get final results by status
     */
    public function getResultsByStatus(int $semesterId, ?string $status = null): Collection
    {
        $query = SemesterResult::where('semester_id', $semesterId)
            ->with(['student', 'semester'])
            ->orderByDesc('average');

        if ($status) {
            $query->where('final_status', $status);
        }

        return $query->get();
    }

    /**
     * Get failed modules for a student (debts)
     */
    public function getStudentDebts(int $studentId, int $semesterId): Collection
    {
        $config = GradeConfig::getConfig();

        return ModuleGrade::where([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
        ])
            ->with('module')
            ->where(function ($q) use ($config) {
                $q->where('average', '<', $config->min_module_average ?? 10.00)
                    ->orWhereNull('average');
            })
            ->where('status', '!=', 'Compensated')
            ->get();
    }

    /**
     * Lock academic year for a semester
     */
    public function lockAcademicYear(int $semesterId): array
    {
        $semester = Semester::find($semesterId);

        // Check if already locked
        $alreadyLocked = SemesterResult::where('semester_id', $semesterId)
            ->whereNotNull('year_locked_at')
            ->exists();

        if ($alreadyLocked) {
            return [
                'success' => false,
                'message' => 'L\'année académique est déjà verrouillée.',
            ];
        }

        // Check if final results are published
        $unpublished = SemesterResult::where('semester_id', $semesterId)
            ->whereNull('final_published_at')
            ->count();

        if ($unpublished > 0) {
            return [
                'success' => false,
                'message' => "Impossible de verrouiller: {$unpublished} résultat(s) non publié(s).",
            ];
        }

        DB::beginTransaction();
        try {
            SemesterResult::where('semester_id', $semesterId)
                ->update(['year_locked_at' => now()]);

            // Archive semester if needed
            if ($semester) {
                $semester->update(['is_archived' => true]);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Année académique verrouillée avec succès.',
                'locked_at' => now()->toIso8601String(),
                'results_locked' => SemesterResult::where('semester_id', $semesterId)->count(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Check if semester is locked
     */
    public function isSemesterLocked(int $semesterId): bool
    {
        return SemesterResult::where('semester_id', $semesterId)
            ->whereNotNull('year_locked_at')
            ->exists();
    }

    /**
     * Get student's final result
     */
    public function getStudentFinalResult(int $studentId, int $semesterId): ?array
    {
        $result = SemesterResult::where([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
        ])
            ->with(['student', 'semester'])
            ->first();

        if (! $result) {
            return null;
        }

        $debts = $this->getStudentDebts($studentId, $semesterId);

        return [
            'id' => $result->id,
            'student' => [
                'id' => $result->student_id,
                'matricule' => $result->student?->matricule,
                'full_name' => $result->student?->full_name,
            ],
            'semester' => [
                'id' => $result->semester_id,
                'name' => $result->semester?->name,
            ],
            'average' => $result->average,
            'mention' => $result->mention,
            'rank' => $result->rank,
            'total_ranked' => $result->total_ranked,
            'rank_display' => $result->rank_display,
            'total_credits' => $result->total_credits,
            'acquired_credits' => $result->acquired_credits,
            'missing_credits' => $result->missing_credits,
            'success_rate' => $result->success_rate,
            'final_status' => $result->final_status,
            'final_status_label' => $result->final_status_label,
            'final_status_color' => $result->final_status_color,
            'can_progress_next_year' => $result->can_progress_next_year,
            'is_final_published' => $result->is_final_published,
            'final_published_at' => $result->final_published_at?->toIso8601String(),
            'is_year_locked' => $result->is_year_locked,
            'year_locked_at' => $result->year_locked_at?->toIso8601String(),
            'attestation_available' => $result->attestation_file_path !== null,
            'attestation_path' => $result->attestation_file_path,
            'debts' => $debts->map(fn ($mg) => [
                'module_id' => $mg->module_id,
                'module_name' => $mg->module?->name,
                'module_code' => $mg->module?->code,
                'average' => $mg->average,
                'credits' => $mg->module?->credits_ects,
            ]),
        ];
    }
}
