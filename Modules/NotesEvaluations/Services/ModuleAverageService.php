<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\StudentModuleEnrollment;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Jobs\RecalculateModuleAveragesJob;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;

class ModuleAverageService
{
    /**
     * Calculate module average for a student
     */
    public function calculate(int $studentId, int $moduleId, int $semesterId, bool $finalOnly = false): ?float
    {
        $evaluations = ModuleEvaluationConfig::where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->get();

        if ($evaluations->isEmpty()) {
            return null;
        }

        $totalWeightedScore = 0;
        $totalCoefficient = 0;
        $missingCount = 0;
        $allAbsent = true;

        foreach ($evaluations as $evaluation) {
            $grade = Grade::where([
                'student_id' => $studentId,
                'evaluation_id' => $evaluation->id,
            ])->first();

            // No grade entered
            if (! $grade) {
                $missingCount++;

                continue;
            }

            // Absent (according to policy)
            if ($grade->is_absent) {
                $absence = $grade->absence;

                if ($absence && $absence->applies_zero_grade) {
                    // Policy: absence = 0
                    $totalWeightedScore += 0 * $evaluation->coefficient;
                    $totalCoefficient += $evaluation->coefficient;
                    $allAbsent = false;
                } else {
                    // Policy: absence excluded
                    continue;
                }
            } else {
                // Normal grade
                $totalWeightedScore += $grade->score * $evaluation->coefficient;
                $totalCoefficient += $evaluation->coefficient;
                $allAbsent = false;
            }
        }

        // If all evaluations are absent
        if ($allAbsent) {
            $this->storeResult($studentId, $moduleId, $semesterId, null, $missingCount === 0, $missingCount);

            return null;
        }

        // If provisional average and finalOnly=true
        if ($finalOnly && $missingCount > 0) {
            return null;
        }

        // Final calculation
        $average = $totalCoefficient > 0
            ? round($totalWeightedScore / $totalCoefficient, 2)
            : null;

        // Store in DB
        $this->storeResult($studentId, $moduleId, $semesterId, $average, $missingCount === 0, $missingCount);

        // Cache
        $this->cacheResult($studentId, $moduleId, $semesterId, $average);

        return $average;
    }

    /**
     * Store calculation result in database
     */
    protected function storeResult(
        int $studentId,
        int $moduleId,
        int $semesterId,
        ?float $average,
        bool $isFinal,
        int $missingCount
    ): ModuleGrade {
        $status = 'Provisoire';
        if ($average === null && $isFinal) {
            $status = 'ABS';
        } elseif ($isFinal) {
            $status = 'Final';
        }

        return ModuleGrade::updateOrCreate(
            [
                'student_id' => $studentId,
                'module_id' => $moduleId,
                'semester_id' => $semesterId,
            ],
            [
                'average' => $average,
                'is_final' => $isFinal,
                'missing_evaluations_count' => $missingCount,
                'status' => $status,
                'calculated_at' => now(),
            ]
        );
    }

    /**
     * Cache the result
     */
    protected function cacheResult(int $studentId, int $moduleId, int $semesterId, ?float $average): void
    {
        $key = "module_avg:{$studentId}:{$moduleId}:{$semesterId}";

        Cache::put($key, $average, now()->addHours(24));
    }

    /**
     * Get cached result or calculate
     */
    public function getCached(int $studentId, int $moduleId, int $semesterId): ?float
    {
        $key = "module_avg:{$studentId}:{$moduleId}:{$semesterId}";

        return Cache::remember(
            $key,
            now()->addHours(24),
            fn () => $this->calculate($studentId, $moduleId, $semesterId)
        );
    }

    /**
     * Invalidate cache for a student/module
     */
    public function invalidateCache(int $studentId, int $moduleId, int $semesterId): void
    {
        $key = "module_avg:{$studentId}:{$moduleId}:{$semesterId}";
        Cache::forget($key);
    }

    /**
     * Recalculate averages for all students in a module
     */
    public function recalculateForModule(int $moduleId, int $semesterId): void
    {
        $studentIds = StudentModuleEnrollment::where('module_id', $moduleId)
            ->pluck('student_id')
            ->unique();

        // If >100 students, use async job
        if ($studentIds->count() > 100) {
            RecalculateModuleAveragesJob::dispatch($moduleId, $semesterId, $studentIds->toArray());
        } else {
            foreach ($studentIds as $studentId) {
                $this->calculate($studentId, $moduleId, $semesterId);
            }
        }
    }

    /**
     * Recalculate all module averages for a semester
     */
    public function recalculateForSemester(int $semesterId): void
    {
        $evaluations = ModuleEvaluationConfig::where('semester_id', $semesterId)
            ->select('module_id')
            ->distinct()
            ->pluck('module_id');

        foreach ($evaluations as $moduleId) {
            $this->recalculateForModule($moduleId, $semesterId);
        }
    }

    /**
     * Get module grades for a student in a semester
     */
    public function getStudentModuleGrades(int $studentId, int $semesterId): array
    {
        return ModuleGrade::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->with(['module'])
            ->get()
            ->map(function ($moduleGrade) {
                return [
                    'module_id' => $moduleGrade->module_id,
                    'module_name' => $moduleGrade->module->name,
                    'module_code' => $moduleGrade->module->code,
                    'credits_ects' => $moduleGrade->module->credits_ects,
                    'average' => $moduleGrade->average,
                    'status' => $moduleGrade->status_label,
                    'is_final' => $moduleGrade->is_final,
                    'is_validated' => $moduleGrade->is_validated,
                    'missing_evaluations' => $moduleGrade->missing_evaluations_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get statistics for a module
     */
    public function getModuleStatistics(int $moduleId, int $semesterId): array
    {
        $grades = ModuleGrade::where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->whereNotNull('average')
            ->get();

        if ($grades->isEmpty()) {
            return [
                'count' => 0,
                'average' => null,
                'min' => null,
                'max' => null,
                'validated_count' => 0,
                'validation_rate' => 0,
            ];
        }

        $averages = $grades->pluck('average');
        $validatedCount = $grades->where('average', '>=', 10)->count();

        return [
            'count' => $grades->count(),
            'average' => round($averages->avg(), 2),
            'min' => round($averages->min(), 2),
            'max' => round($averages->max(), 2),
            'validated_count' => $validatedCount,
            'validation_rate' => round(($validatedCount / $grades->count()) * 100, 2),
        ];
    }
}
