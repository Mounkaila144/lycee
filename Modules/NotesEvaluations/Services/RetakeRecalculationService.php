<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\EctsAllocation;
use Modules\NotesEvaluations\Entities\GradeConfig;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Entities\RecalculationLog;
use Modules\NotesEvaluations\Entities\RetakeEnrollment;
use Modules\NotesEvaluations\Entities\SemesterResult;
use Modules\NotesEvaluations\Jobs\RecalculateAfterRetakeJob;
use Modules\NotesEvaluations\Notifications\RetakeResultsNotification;

class RetakeRecalculationService
{
    /**
     * Recalculate all averages and statuses after retake grades are published
     */
    public function recalculateAfterRetake(int $studentId, int $semesterId): array
    {
        $config = GradeConfig::getConfig();

        // Get current state for logging
        $semesterResult = SemesterResult::where([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
        ])->first();

        $oldSemesterAverage = $semesterResult?->average;
        $oldSemesterStatus = $semesterResult?->global_status;
        $oldCredits = $semesterResult?->acquired_credits;

        $moduleChanges = [];

        DB::beginTransaction();
        try {
            // 1. Recalculate module averages with retake grades
            $moduleChanges = $this->recalculateModuleAverages($studentId, $semesterId, $config);

            // 2. Recalculate semester average
            $newSemesterAverage = $this->recalculateSemesterAverage($studentId, $semesterId);

            // 3. Recalculate ECTS credits
            $newCredits = $this->recalculateCredits($studentId, $semesterId, $config);

            // 4. Update global status
            $newStatus = $this->updateGlobalStatus($studentId, $semesterId, $config);

            // 5. Create recalculation log
            $this->createRecalculationLog(
                $studentId,
                $semesterId,
                $oldSemesterAverage,
                $newSemesterAverage,
                $oldSemesterStatus,
                $newStatus,
                $oldCredits,
                $newCredits,
                $moduleChanges
            );

            DB::commit();

            // 6. Notify student
            $this->notifyStudent($studentId, $semesterId);

            return [
                'student_id' => $studentId,
                'semester_id' => $semesterId,
                'modules_updated' => count($moduleChanges),
                'old_semester_average' => $oldSemesterAverage,
                'new_semester_average' => $newSemesterAverage,
                'old_status' => $oldSemesterStatus,
                'new_status' => $newStatus,
                'credits_before' => $oldCredits,
                'credits_after' => $newCredits,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Recalculate module averages with retake grades
     */
    protected function recalculateModuleAverages(int $studentId, int $semesterId, GradeConfig $config): array
    {
        $retakeEnrollments = RetakeEnrollment::where([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
        ])
            ->whereHas('retakeGrade', fn ($q) => $q->where('status', 'published'))
            ->with('retakeGrade')
            ->get();

        $changes = [];

        foreach ($retakeEnrollments as $enrollment) {
            $retakeGrade = $enrollment->retakeGrade;

            if (! $retakeGrade) {
                continue;
            }

            $moduleGrade = ModuleGrade::where([
                'student_id' => $studentId,
                'module_id' => $enrollment->module_id,
                'semester_id' => $semesterId,
            ])->first();

            if (! $moduleGrade) {
                continue;
            }

            $originalAverage = $moduleGrade->average;
            $retakeScore = $retakeGrade->is_absent ? null : $retakeGrade->score;

            // New average = MAX(original, retake)
            $newAverage = $originalAverage;
            $improved = false;

            if ($retakeScore !== null && $retakeScore > ($originalAverage ?? 0)) {
                $newAverage = $retakeScore;
                $improved = true;
            }

            // Determine new status
            $newStatus = $newAverage >= ($config->min_module_average ?? 10.00) ? 'Final' : $moduleGrade->status;

            // Store original average if not already stored
            $originalBeforeRetake = $moduleGrade->original_average_before_retake ?? $originalAverage;

            $moduleGrade->update([
                'average' => $newAverage,
                'has_retake_grade' => true,
                'retake_improved' => $improved,
                'original_average_before_retake' => $originalBeforeRetake,
                'status' => $newStatus,
                'calculated_at' => now(),
            ]);

            // Update enrollment status
            if ($newAverage >= ($config->min_module_average ?? 10.00)) {
                $enrollment->markAsValidated();
            }

            $changes[] = [
                'module_id' => $enrollment->module_id,
                'old_average' => $originalAverage,
                'new_average' => $newAverage,
                'retake_score' => $retakeScore,
                'improved' => $improved,
            ];
        }

        return $changes;
    }

    /**
     * Recalculate semester average
     */
    protected function recalculateSemesterAverage(int $studentId, int $semesterId): ?float
    {
        $moduleGrades = ModuleGrade::where([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
        ])
            ->with('module')
            ->get();

        if ($moduleGrades->isEmpty()) {
            return null;
        }

        $totalWeighted = 0;
        $totalCredits = 0;

        foreach ($moduleGrades as $moduleGrade) {
            $credits = $moduleGrade->module?->credits_ects ?? 1;

            if ($moduleGrade->average !== null) {
                $totalWeighted += $moduleGrade->average * $credits;
                $totalCredits += $credits;
            }
        }

        $newAverage = $totalCredits > 0 ? round($totalWeighted / $totalCredits, 2) : null;

        // Update semester result
        $semesterResult = SemesterResult::where([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
        ])->first();

        if ($semesterResult) {
            $semesterResult->update([
                'average' => $newAverage,
                'calculated_at' => now(),
            ]);
        }

        return $newAverage;
    }

    /**
     * Recalculate ECTS credits
     */
    protected function recalculateCredits(int $studentId, int $semesterId, GradeConfig $config): int
    {
        $moduleGrades = ModuleGrade::where([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
        ])
            ->with('module')
            ->get();

        $acquiredCredits = 0;
        $totalCredits = 0;

        foreach ($moduleGrades as $moduleGrade) {
            $credits = $moduleGrade->module?->credits_ects ?? 0;
            $totalCredits += $credits;

            // Credits acquired if module validated or compensated
            if ($moduleGrade->average !== null && $moduleGrade->average >= ($config->min_module_average ?? 10.00)) {
                $acquiredCredits += $credits;

                // Update or create ECTS allocation
                EctsAllocation::updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'module_id' => $moduleGrade->module_id,
                        'semester_id' => $semesterId,
                    ],
                    [
                        'credits_earned' => $credits,
                        'validation_type' => $moduleGrade->has_retake_grade ? 'retake' : 'normal',
                        'allocated_at' => now(),
                    ]
                );
            } elseif ($moduleGrade->status === 'Compensated') {
                $acquiredCredits += $credits;

                EctsAllocation::updateOrCreate(
                    [
                        'student_id' => $studentId,
                        'module_id' => $moduleGrade->module_id,
                        'semester_id' => $semesterId,
                    ],
                    [
                        'credits_earned' => $credits,
                        'validation_type' => 'compensation',
                        'allocated_at' => now(),
                    ]
                );
            }
        }

        // Update semester result
        SemesterResult::where([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
        ])->update([
            'total_credits' => $totalCredits,
            'acquired_credits' => $acquiredCredits,
            'missing_credits' => $totalCredits - $acquiredCredits,
            'success_rate' => $totalCredits > 0 ? round(($acquiredCredits / $totalCredits) * 100, 2) : 0,
        ]);

        return $acquiredCredits;
    }

    /**
     * Update global status after retake
     */
    protected function updateGlobalStatus(int $studentId, int $semesterId, GradeConfig $config): string
    {
        $semesterResult = SemesterResult::where([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
        ])->first();

        if (! $semesterResult) {
            return 'unknown';
        }

        $moduleGrades = ModuleGrade::where([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
        ])->get();

        $failedModules = $moduleGrades->filter(function ($mg) use ($config) {
            return ($mg->average === null || $mg->average < ($config->min_module_average ?? 10.00))
                && $mg->status !== 'Compensated';
        })->count();

        $semesterValidated = $semesterResult->average >= ($config->min_semester_average ?? 10.00);

        // Determine new status
        $newStatus = match (true) {
            $failedModules === 0 && $semesterValidated => 'validated',
            $failedModules > 0 && $semesterValidated => 'partially_validated',
            default => 'failed_after_retake',
        };

        $semesterResult->update([
            'global_status' => $newStatus,
            'failed_modules_count' => $failedModules,
            'retake_session_completed' => true,
        ]);

        return $newStatus;
    }

    /**
     * Create recalculation log entry
     */
    protected function createRecalculationLog(
        int $studentId,
        int $semesterId,
        ?float $oldSemesterAverage,
        ?float $newSemesterAverage,
        ?string $oldStatus,
        string $newStatus,
        ?int $oldCredits,
        int $newCredits,
        array $moduleChanges
    ): void {
        RecalculationLog::create([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
            'trigger' => 'retake_grades_published',
            'old_semester_average' => $oldSemesterAverage,
            'new_semester_average' => $newSemesterAverage,
            'old_semester_status' => $oldStatus,
            'new_semester_status' => $newStatus,
            'credits_before' => $oldCredits,
            'credits_after' => $newCredits,
            'details' => [
                'modules_updated' => count($moduleChanges),
                'module_changes' => $moduleChanges,
            ],
            'recalculated_at' => now(),
        ]);
    }

    /**
     * Notify student of retake results
     */
    protected function notifyStudent(int $studentId, int $semesterId): void
    {
        $student = Student::find($studentId);
        $semesterResult = SemesterResult::where([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
        ])->first();

        if ($student && $semesterResult) {
            $student->notify(new RetakeResultsNotification($semesterResult));
        }
    }

    /**
     * Recalculate for all students in a semester
     */
    public function recalculateAllStudents(int $semesterId, bool $async = true): array
    {
        $studentIds = RetakeEnrollment::where('semester_id', $semesterId)
            ->whereHas('retakeGrade', fn ($q) => $q->where('status', 'published'))
            ->pluck('student_id')
            ->unique();

        if ($studentIds->isEmpty()) {
            return [
                'message' => 'Aucun étudiant avec notes de rattrapage publiées.',
                'count' => 0,
            ];
        }

        if ($async && $studentIds->count() > 50) {
            RecalculateAfterRetakeJob::dispatch($semesterId, $studentIds->toArray());

            return [
                'message' => 'Recalcul lancé en arrière-plan.',
                'count' => $studentIds->count(),
                'status' => 'queued',
            ];
        }

        $results = [];
        foreach ($studentIds as $studentId) {
            $results[] = $this->recalculateAfterRetake($studentId, $semesterId);
        }

        return [
            'message' => 'Recalcul terminé.',
            'count' => count($results),
            'results' => $results,
        ];
    }

    /**
     * Get recalculation logs for a semester
     */
    public function getRecalculationLogs(int $semesterId, ?int $studentId = null): Collection
    {
        $query = RecalculationLog::where('semester_id', $semesterId)
            ->with(['student', 'module'])
            ->orderByDesc('recalculated_at');

        if ($studentId) {
            $query->where('student_id', $studentId);
        }

        return $query->get();
    }
}
