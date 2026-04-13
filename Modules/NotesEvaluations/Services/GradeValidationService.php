<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Enrollment\Entities\StudentModuleEnrollment;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\NotesEvaluations\Entities\GradeValidation;
use Modules\NotesEvaluations\Jobs\PublishGradesJob;
use Modules\NotesEvaluations\Notifications\GradesPublishedNotification;
use Modules\NotesEvaluations\Notifications\GradesSubmittedNotification;
use Modules\NotesEvaluations\Notifications\GradesValidatedNotification;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\UsersGuard\Entities\TenantUser;

class GradeValidationService
{
    public function __construct(
        private GradeStatisticsService $statisticsService
    ) {}

    /**
     * Submit grades for validation
     */
    public function submitForValidation(
        Module $module,
        ?ModuleEvaluationConfig $evaluation,
        TenantUser $teacher
    ): GradeValidation {
        // Pre-submission checks
        $checks = $this->performPreSubmissionChecks($module, $evaluation);

        if (! $checks['can_submit']) {
            throw new \Exception('Impossible de soumettre: '.implode(', ', $checks['errors']));
        }

        return DB::transaction(function () use ($module, $evaluation, $teacher) {
            // Update grade statuses
            $query = Grade::whereHas('evaluation', function ($q) use ($module) {
                $q->where('module_id', $module->id);
            });

            if ($evaluation) {
                $query->where('evaluation_id', $evaluation->id);
            }

            $query->where('status', 'Draft')
                ->update(['status' => 'Submitted']);

            // Calculate statistics
            $stats = $evaluation
                ? $this->statisticsService->calculateStats($evaluation)
                : $this->statisticsService->getModuleSummary($module->id);

            $anomalies = $this->statisticsService->detectAnomalies($stats);

            // Get current academic year
            $academicYear = AcademicYear::where('is_active', true)->first();

            // Create validation request
            $validation = GradeValidation::create([
                'module_id' => $module->id,
                'evaluation_id' => $evaluation?->id,
                'academic_year_id' => $academicYear?->id,
                'semester_id' => $evaluation?->semester_id,
                'submitted_by' => $teacher->id,
                'status' => 'Pending',
                'submitted_at' => now(),
                'statistics' => $stats,
                'anomalies' => $anomalies,
            ]);

            // Notify pedagogical heads
            $this->notifyPedagogicalHeads($validation);

            return $validation;
        });
    }

    /**
     * Validate/Approve grades
     */
    public function validateGrades(
        GradeValidation $validation,
        TenantUser $validator,
        string $decision,
        ?string $notes = null
    ): void {
        if (! $validation->canBeValidated()) {
            throw new \Exception('Cette demande ne peut plus être validée.');
        }

        DB::transaction(function () use ($validation, $validator, $decision, $notes) {
            if ($decision === 'Approved') {
                $validation->approve($validator, $notes);

                // Update grade statuses
                $this->updateGradeStatuses($validation, 'Validated');
            } else {
                $validation->reject($validator, $notes ?? 'Aucun motif fourni');

                // Revert grade statuses to Draft
                $this->updateGradeStatuses($validation, 'Draft');
            }
        });

        // Notify teacher (outside transaction to avoid rollback on notification failure)
        try {
            $validation->submitter->notify(new GradesValidatedNotification($validation, $decision));
        } catch (\Exception $e) {
            logger()->warning('Failed to send grade validation notification: '.$e->getMessage());
        }
    }

    /**
     * Publish grades
     */
    public function publishGrades(
        GradeValidation $validation,
        ?\DateTimeInterface $publishAt = null
    ): void {
        if (! $validation->canBePublished()) {
            throw new \Exception('Seules les notes validées peuvent être publiées.');
        }

        if ($publishAt && $publishAt > now()) {
            // Scheduled publication
            $validation->update(['scheduled_publish_at' => $publishAt]);
            PublishGradesJob::dispatch($validation->id)->delay($publishAt);

            return;
        }

        DB::transaction(function () use ($validation) {
            $validation->publish();

            // Update grades
            $this->updateGradeStatuses($validation, 'Published');

            // Make grades visible to students
            $query = Grade::whereHas('evaluation', function ($q) use ($validation) {
                $q->where('module_id', $validation->module_id);
            });

            if ($validation->evaluation_id) {
                $query->where('evaluation_id', $validation->evaluation_id);
            }

            $query->update([
                'is_visible_to_students' => true,
                'published_at' => now(),
            ]);

            // Notify students
            $this->notifyStudents($validation);
        });
    }

    /**
     * Bulk publish multiple validations
     */
    public function bulkPublish(array $validationIds, TenantUser $publisher): array
    {
        $results = [
            'published' => 0,
            'errors' => [],
        ];

        foreach ($validationIds as $id) {
            try {
                $validation = GradeValidation::find($id);
                if ($validation && $validation->canBePublished()) {
                    $this->publishGrades($validation);
                    $results['published']++;
                }
            } catch (\Exception $e) {
                $results['errors'][] = "Validation {$id}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Pre-submission checks
     *
     * @return array{can_submit: bool, errors: array, warnings: array}
     */
    public function performPreSubmissionChecks(Module $module, ?ModuleEvaluationConfig $evaluation): array
    {
        $checks = [
            'can_submit' => true,
            'errors' => [],
            'warnings' => [],
        ];

        // Get total students enrolled (unique students only)
        $totalStudents = StudentModuleEnrollment::forModule($module->id)
            ->when($evaluation?->semester_id, fn ($q) => $q->bySemester($evaluation->semester_id))
            ->inscrit()
            ->distinct('student_id')
            ->count('student_id');

        // Count grades entered
        $query = Grade::whereHas('evaluation', function ($q) use ($module) {
            $q->where('module_id', $module->id);
        });

        if ($evaluation) {
            $query->where('evaluation_id', $evaluation->id);
        }

        $gradedStudents = $query->distinct('student_id')->count('student_id');

        // Check completeness
        if ($gradedStudents < $totalStudents) {
            $missing = $totalStudents - $gradedStudents;
            $checks['can_submit'] = false;
            $checks['errors'][] = "Notes manquantes: {$gradedStudents}/{$totalStudents} étudiants notés ({$missing} manquants)";
        }

        // Check for invalid grades
        $invalidGrades = (clone $query)->where(function ($q) {
            $q->where('score', '<', 0)
                ->orWhere('score', '>', 20);
        })->where('is_absent', false)->count();

        if ($invalidGrades > 0) {
            $checks['can_submit'] = false;
            $checks['errors'][] = "{$invalidGrades} note(s) invalide(s) (hors plage 0-20)";
        }

        // Statistics warnings
        if ($evaluation) {
            $stats = $this->statisticsService->calculateStats($evaluation);
            $anomalies = $this->statisticsService->detectAnomalies($stats);

            if (! empty($anomalies)) {
                $checks['warnings'] = $anomalies;
            }
        }

        return $checks;
    }

    /**
     * Update grade statuses for a validation
     */
    private function updateGradeStatuses(GradeValidation $validation, string $status): void
    {
        $query = Grade::whereHas('evaluation', function ($q) use ($validation) {
            $q->where('module_id', $validation->module_id);
        });

        if ($validation->evaluation_id) {
            $query->where('evaluation_id', $validation->evaluation_id);
        }

        $query->update(['status' => $status]);
    }

    /**
     * Notify pedagogical heads about new submission
     */
    private function notifyPedagogicalHeads(GradeValidation $validation): void
    {
        try {
            $heads = TenantUser::role('Administrator')->get();

            foreach ($heads as $head) {
                $head->notify(new GradesSubmittedNotification($validation));
            }
        } catch (\Exception $e) {
            // Role doesn't exist or no users with this role - silently continue
            logger()->warning('Could not notify pedagogical heads: '.$e->getMessage());
        }
    }

    /**
     * Notify students about published grades
     */
    private function notifyStudents(GradeValidation $validation): void
    {
        $students = $this->getAffectedStudents($validation);

        Notification::send($students, new GradesPublishedNotification($validation));
    }

    /**
     * Get students affected by a validation
     */
    private function getAffectedStudents(GradeValidation $validation): Collection
    {
        $query = Grade::whereHas('evaluation', function ($q) use ($validation) {
            $q->where('module_id', $validation->module_id);
        });

        if ($validation->evaluation_id) {
            $query->where('evaluation_id', $validation->evaluation_id);
        }

        return $query->with('student')
            ->get()
            ->pluck('student')
            ->unique('id');
    }

    /**
     * Get validation statistics dashboard
     */
    public function getValidationStatistics(?int $academicYearId = null): array
    {
        $query = GradeValidation::query();

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        return [
            'total' => $query->count(),
            'pending' => (clone $query)->pending()->count(),
            'approved' => (clone $query)->approved()->count(),
            'rejected' => (clone $query)->rejected()->count(),
            'published' => (clone $query)->published()->count(),
            'average_validation_time' => $this->calculateAverageValidationTime($query),
            'rejection_rate' => $this->calculateRejectionRate($query),
        ];
    }

    /**
     * Calculate average validation time in hours
     */
    private function calculateAverageValidationTime($query): float
    {
        $validated = (clone $query)
            ->whereNotNull('validated_at')
            ->get();

        if ($validated->isEmpty()) {
            return 0;
        }

        $totalHours = $validated->sum(function ($v) {
            return $v->submitted_at->diffInHours($v->validated_at);
        });

        return round($totalHours / $validated->count(), 1);
    }

    /**
     * Calculate rejection rate percentage
     */
    private function calculateRejectionRate($query): float
    {
        $total = (clone $query)->whereIn('status', ['Approved', 'Rejected'])->count();

        if ($total === 0) {
            return 0;
        }

        $rejected = (clone $query)->rejected()->count();

        return round(($rejected / $total) * 100, 1);
    }
}
