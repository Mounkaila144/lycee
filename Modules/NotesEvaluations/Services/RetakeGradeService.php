<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Entities\RetakeEnrollment;
use Modules\NotesEvaluations\Entities\RetakeGrade;

class RetakeGradeService
{
    /**
     * Store or update a retake grade
     */
    public function storeGrade(int $retakeEnrollmentId, ?float $score, bool $isAbsent, ?string $comment = null): RetakeGrade
    {
        return RetakeGrade::updateOrCreate(
            ['retake_enrollment_id' => $retakeEnrollmentId],
            [
                'score' => $isAbsent ? null : $score,
                'is_absent' => $isAbsent,
                'entered_by' => auth()->id(),
                'entered_at' => now(),
                'comment' => $comment,
            ]
        );
    }

    /**
     * Store multiple retake grades at once
     */
    public function storeBatchGrades(array $grades): Collection
    {
        $results = collect();

        DB::beginTransaction();
        try {
            foreach ($grades as $gradeData) {
                $grade = $this->storeGrade(
                    $gradeData['retake_enrollment_id'],
                    $gradeData['score'] ?? null,
                    $gradeData['is_absent'] ?? false,
                    $gradeData['comment'] ?? null
                );
                $results->push($grade);
            }

            DB::commit();

            return $results;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calculate new average after retake
     */
    public function calculateNewAverage(RetakeEnrollment $enrollment, ?float $retakeScore): ?float
    {
        if ($retakeScore === null) {
            return $enrollment->original_average;
        }

        return max($enrollment->original_average ?? 0, $retakeScore);
    }

    /**
     * Submit grades for validation
     */
    public function submitGrades(int $moduleId, int $semesterId): int
    {
        $enrollments = RetakeEnrollment::where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->with('retakeGrade')
            ->get();

        $submitted = 0;

        foreach ($enrollments as $enrollment) {
            if ($enrollment->retakeGrade && $enrollment->retakeGrade->canBeSubmitted()) {
                $enrollment->retakeGrade->submit();
                $submitted++;
            }
        }

        return $submitted;
    }

    /**
     * Validate grades (admin)
     */
    public function validateGrades(int $moduleId, int $semesterId): int
    {
        $enrollments = RetakeEnrollment::where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->with('retakeGrade')
            ->get();

        $validated = 0;

        foreach ($enrollments as $enrollment) {
            if ($enrollment->retakeGrade && $enrollment->retakeGrade->canBeValidated()) {
                $enrollment->retakeGrade->validate();
                $validated++;
            }
        }

        return $validated;
    }

    /**
     * Publish retake grades
     */
    public function publishGrades(int $moduleId, int $semesterId): array
    {
        $enrollments = RetakeEnrollment::where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->with('retakeGrade')
            ->get();

        $published = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($enrollments as $enrollment) {
                if (! $enrollment->retakeGrade) {
                    continue;
                }

                if (! $enrollment->retakeGrade->canBePublished()) {
                    $errors[] = "Note pour l'étudiant {$enrollment->student_id} non publiable.";

                    continue;
                }

                $enrollment->retakeGrade->publish();

                // Update module grade with new average
                $this->updateModuleGrade($enrollment);

                $published++;
            }

            DB::commit();

            return [
                'published' => $published,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update module grade with retake result
     */
    protected function updateModuleGrade(RetakeEnrollment $enrollment): void
    {
        $retakeGrade = $enrollment->retakeGrade;

        if (! $retakeGrade) {
            return;
        }

        $newAverage = $this->calculateNewAverage($enrollment, $retakeGrade->effective_score);

        ModuleGrade::updateOrCreate(
            [
                'student_id' => $enrollment->student_id,
                'module_id' => $enrollment->module_id,
                'semester_id' => $enrollment->semester_id,
            ],
            [
                'average' => $newAverage,
                'is_final' => true,
                'status' => $newAverage >= 10 ? 'Final' : 'Final',
                'calculated_at' => now(),
            ]
        );

        // Update enrollment status
        if ($newAverage >= 10) {
            $enrollment->markAsValidated();
        }
    }

    /**
     * Get retake students for a module with their grades
     */
    public function getModuleRetakeStudents(int $moduleId, int $semesterId): Collection
    {
        return RetakeEnrollment::where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->with(['student', 'retakeGrade', 'module'])
            ->orderBy('student_id')
            ->get()
            ->map(function ($enrollment) {
                $retakeGrade = $enrollment->retakeGrade;

                return [
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'matricule' => $enrollment->student?->matricule,
                    'firstname' => $enrollment->student?->firstname,
                    'lastname' => $enrollment->student?->lastname,
                    'full_name' => ($enrollment->student?->firstname ?? '').' '.($enrollment->student?->lastname ?? ''),
                    'original_average' => $enrollment->original_average,
                    'retake_grade_id' => $retakeGrade?->id,
                    'retake_score' => $retakeGrade?->score,
                    'is_absent' => $retakeGrade?->is_absent ?? false,
                    'new_average' => $retakeGrade?->new_average ?? $enrollment->original_average,
                    'is_improved' => $retakeGrade?->is_improved ?? false,
                    'improvement_amount' => $retakeGrade?->improvement_amount,
                    'status' => $retakeGrade?->status ?? 'not_entered',
                    'status_label' => $retakeGrade?->status_label ?? 'Non saisi',
                    'enrollment_status' => $enrollment->status,
                ];
            });
    }

    /**
     * Get statistics for a module's retake grades
     */
    public function getStatistics(int $moduleId, int $semesterId): array
    {
        $enrollments = RetakeEnrollment::where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->with('retakeGrade')
            ->get();

        $total = $enrollments->count();

        $graded = $enrollments->filter(fn ($e) => $e->retakeGrade !== null && ($e->retakeGrade->score !== null || $e->retakeGrade->is_absent))->count();

        $improved = $enrollments->filter(function ($enrollment) {
            $retakeGrade = $enrollment->retakeGrade;
            if (! $retakeGrade || $retakeGrade->is_absent || $retakeGrade->score === null) {
                return false;
            }

            return $retakeGrade->score > ($enrollment->original_average ?? 0);
        })->count();

        $newAverages = $enrollments->map(function ($enrollment) {
            $retakeGrade = $enrollment->retakeGrade;
            if (! $retakeGrade || $retakeGrade->is_absent) {
                return $enrollment->original_average;
            }

            return $this->calculateNewAverage($enrollment, $retakeGrade->score);
        });

        $originalPassed = $enrollments->filter(fn ($e) => ($e->original_average ?? 0) >= 10)->count();
        $passed = $newAverages->filter(fn ($avg) => $avg >= 10)->count();

        $retakeScores = $enrollments
            ->pluck('retakeGrade.score')
            ->filter()
            ->values();

        $absentCount = $enrollments->filter(fn ($e) => $e->retakeGrade && $e->retakeGrade->is_absent)->count();

        return [
            'total_students' => $total,
            'graded' => $graded,
            'pending_entry' => $total - $graded,
            'absent' => $absentCount,
            'improved' => $improved,
            'improvement_rate' => $total > 0 ? round(($improved / $total) * 100, 2) : 0,
            'passed_before_retake' => $originalPassed,
            'passed_after_retake' => $passed,
            'new_passes' => $passed - $originalPassed,
            'pass_rate' => $total > 0 ? round(($passed / $total) * 100, 2) : 0,
            'average_retake_score' => $retakeScores->isNotEmpty() ? round($retakeScores->avg(), 2) : null,
            'min_retake_score' => $retakeScores->isNotEmpty() ? round($retakeScores->min(), 2) : null,
            'max_retake_score' => $retakeScores->isNotEmpty() ? round($retakeScores->max(), 2) : null,
        ];
    }

    /**
     * Check if grades can be submitted for a module
     */
    public function canSubmitGrades(int $moduleId, int $semesterId): array
    {
        $enrollments = RetakeEnrollment::where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->with('retakeGrade')
            ->get();

        $total = $enrollments->count();
        $entered = $enrollments->filter(fn ($e) => $e->retakeGrade !== null)->count();
        $submittable = $enrollments->filter(fn ($e) => $e->retakeGrade && $e->retakeGrade->canBeSubmitted())->count();

        return [
            'can_submit' => $submittable > 0,
            'total_students' => $total,
            'grades_entered' => $entered,
            'submittable_count' => $submittable,
            'missing_grades' => $total - $entered,
        ];
    }
}
