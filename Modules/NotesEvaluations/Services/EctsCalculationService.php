<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Facades\DB;
use Modules\NotesEvaluations\Entities\EctsAllocation;
use Modules\NotesEvaluations\Entities\GradeConfig;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Entities\SemesterResult;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\ModuleSemesterAssignment;

class EctsCalculationService
{
    /**
     * Calculate ECTS credits for a semester
     */
    public function calculateForSemester(int $studentId, int $semesterId): array
    {
        $moduleIds = ModuleSemesterAssignment::where('semester_id', $semesterId)
            ->where('is_active', true)
            ->pluck('module_id')
            ->unique();

        $modules = Module::whereIn('id', $moduleIds)->get();

        $totalCredits = $modules->sum('credits_ects');
        $acquiredCredits = 0;
        $allocations = [];

        foreach ($modules as $module) {
            $moduleGrade = ModuleGrade::where([
                'student_id' => $studentId,
                'module_id' => $module->id,
                'semester_id' => $semesterId,
            ])->first();

            if (! $moduleGrade) {
                continue;
            }

            $status = $this->determineModuleStatus($studentId, $module->id, $semesterId);

            if (in_array($status, ['validated', 'compensated'])) {
                $acquiredCredits += $module->credits_ects;

                $allocations[] = [
                    'module_id' => $module->id,
                    'module_name' => $module->name,
                    'credits' => $module->credits_ects,
                    'type' => $status,
                ];
            }
        }

        $missingCredits = $totalCredits - $acquiredCredits;
        $successRate = $totalCredits > 0
            ? round(($acquiredCredits / $totalCredits) * 100, 2)
            : 0;

        return [
            'total_credits' => $totalCredits,
            'acquired_credits' => $acquiredCredits,
            'missing_credits' => $missingCredits,
            'success_rate' => $successRate,
            'allocations' => $allocations,
        ];
    }

    /**
     * Determine module status (validated, compensated, to_retake, absent)
     */
    public function determineModuleStatus(int $studentId, int $moduleId, int $semesterId): string
    {
        $module = Module::findOrFail($moduleId);
        $moduleGrade = ModuleGrade::where([
            'student_id' => $studentId,
            'module_id' => $moduleId,
            'semester_id' => $semesterId,
        ])->first();

        if (! $moduleGrade || $moduleGrade->average === null) {
            return 'absent';
        }

        $config = GradeConfig::getConfig();
        $threshold = $config->min_module_average;

        // Directly validated
        if ($moduleGrade->average >= $threshold) {
            return 'validated';
        }

        // Check if eliminatory
        if ($module->is_eliminatory) {
            return 'to_retake'; // No compensation
        }

        // Check compensation
        if ($config->compensation_enabled) {
            $semesterResult = SemesterResult::where([
                'student_id' => $studentId,
                'semester_id' => $semesterId,
            ])->first();

            if ($semesterResult && $semesterResult->average >= $config->min_semester_average) {
                return 'compensated';
            }
        }

        return 'to_retake';
    }

    /**
     * Allocate ECTS credits for a student in a semester
     */
    public function allocateCredits(int $studentId, int $semesterId): void
    {
        $calculation = $this->calculateForSemester($studentId, $semesterId);

        DB::beginTransaction();
        try {
            // Update SemesterResult
            $semesterResult = SemesterResult::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'semester_id' => $semesterId,
                ],
                [
                    'total_credits' => $calculation['total_credits'],
                    'acquired_credits' => $calculation['acquired_credits'],
                    'missing_credits' => $calculation['missing_credits'],
                    'success_rate' => $calculation['success_rate'],
                ]
            );

            // Delete old allocations for this semester
            EctsAllocation::where('semester_result_id', $semesterResult->id)->delete();

            // Create new allocations
            foreach ($calculation['allocations'] as $allocation) {
                EctsAllocation::create([
                    'student_id' => $studentId,
                    'module_id' => $allocation['module_id'],
                    'credits_allocated' => $allocation['credits'],
                    'allocation_type' => $allocation['type'],
                    'allocated_at' => now(),
                    'semester_result_id' => $semesterResult->id,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Allocate equivalence credits manually
     */
    public function allocateEquivalence(
        int $studentId,
        int $moduleId,
        int $credits,
        ?string $note = null
    ): EctsAllocation {
        // Check if allocation already exists
        $existing = EctsAllocation::where('student_id', $studentId)
            ->where('module_id', $moduleId)
            ->first();

        if ($existing) {
            $existing->update([
                'credits_allocated' => $credits,
                'allocation_type' => EctsAllocation::TYPE_EQUIVALENCE,
                'note' => $note,
                'allocated_at' => now(),
            ]);

            return $existing->fresh();
        }

        return EctsAllocation::create([
            'student_id' => $studentId,
            'module_id' => $moduleId,
            'credits_allocated' => $credits,
            'allocation_type' => EctsAllocation::TYPE_EQUIVALENCE,
            'note' => $note,
            'allocated_at' => now(),
        ]);
    }

    /**
     * Get total acquired credits for a student
     */
    public function getTotalAcquiredCredits(int $studentId): int
    {
        return EctsAllocation::where('student_id', $studentId)->sum('credits_allocated');
    }

    /**
     * Get credits acquired by year level
     */
    public function getCreditsbyYearLevel(int $studentId, string $level): int
    {
        return EctsAllocation::where('student_id', $studentId)
            ->whereHas('module', function ($q) use ($level) {
                $q->where('level', $level);
            })
            ->sum('credits_allocated');
    }

    /**
     * Check if student can progress to next year
     */
    public function canProgressToNextYear(int $studentId, string $currentLevel): array
    {
        $config = GradeConfig::getConfig();
        $requiredPercentage = $config->year_progression_threshold;

        $totalCreditsYear = 60; // LMD standard (2 semesters × 30 ECTS)
        $acquiredCreditsYear = $this->getCreditsbyYearLevel($studentId, $currentLevel);
        $percentage = ($acquiredCreditsYear / $totalCreditsYear) * 100;

        return [
            'can_progress' => $percentage >= $requiredPercentage,
            'acquired_credits' => $acquiredCreditsYear,
            'total_credits' => $totalCreditsYear,
            'percentage' => round($percentage, 2),
            'required_percentage' => $requiredPercentage,
            'missing_credits' => max(0, ceil(($requiredPercentage / 100) * $totalCreditsYear) - $acquiredCreditsYear),
        ];
    }

    /**
     * Get ECTS summary for a student
     */
    public function getStudentEctsSummary(int $studentId): array
    {
        $allocations = EctsAllocation::where('student_id', $studentId)
            ->with(['module', 'semesterResult.semester'])
            ->get();

        $bySemester = $allocations->groupBy(function ($allocation) {
            return $allocation->semesterResult?->semester_id ?? 'equivalence';
        });

        $summary = [];
        foreach ($bySemester as $semesterId => $semesterAllocations) {
            if ($semesterId === 'equivalence') {
                $summary['equivalences'] = [
                    'credits' => $semesterAllocations->sum('credits_allocated'),
                    'modules' => $semesterAllocations->map(function ($a) {
                        return [
                            'module_id' => $a->module_id,
                            'module_name' => $a->module->name,
                            'credits' => $a->credits_allocated,
                            'note' => $a->note,
                        ];
                    })->toArray(),
                ];
            } else {
                $semester = $semesterAllocations->first()->semesterResult?->semester;
                $summary['semesters'][] = [
                    'semester_id' => $semesterId,
                    'semester_name' => $semester?->name,
                    'credits_acquired' => $semesterAllocations->sum('credits_allocated'),
                    'validated_count' => $semesterAllocations->where('allocation_type', 'validated')->count(),
                    'compensated_count' => $semesterAllocations->where('allocation_type', 'compensated')->count(),
                ];
            }
        }

        $summary['total_credits'] = $this->getTotalAcquiredCredits($studentId);

        // Get progression info for each level
        $levels = ['L1', 'L2', 'L3', 'M1', 'M2'];
        $summary['progression'] = [];
        foreach ($levels as $level) {
            $credits = $this->getCreditsbyYearLevel($studentId, $level);
            if ($credits > 0) {
                $summary['progression'][$level] = [
                    'acquired' => $credits,
                    'total' => 60,
                    'percentage' => round(($credits / 60) * 100, 2),
                ];
            }
        }

        return $summary;
    }

    /**
     * Get ECTS allocations for a student in a semester
     */
    public function getStudentSemesterAllocations(int $studentId, int $semesterId): array
    {
        return EctsAllocation::where('student_id', $studentId)
            ->whereHas('semesterResult', function ($q) use ($semesterId) {
                $q->where('semester_id', $semesterId);
            })
            ->with('module')
            ->get()
            ->map(function ($allocation) {
                return [
                    'module_id' => $allocation->module_id,
                    'module_code' => $allocation->module->code,
                    'module_name' => $allocation->module->name,
                    'credits_allocated' => $allocation->credits_allocated,
                    'allocation_type' => $allocation->allocation_type,
                    'allocation_type_label' => $allocation->allocation_type_label,
                    'allocated_at' => $allocation->allocated_at->toIso8601String(),
                ];
            })
            ->toArray();
    }

    /**
     * Get ECTS statistics for a semester
     */
    public function getSemesterEctsStatistics(int $semesterId): array
    {
        $results = SemesterResult::where('semester_id', $semesterId)->get();

        if ($results->isEmpty()) {
            return [
                'total_students' => 0,
                'avg_credits_acquired' => 0,
                'avg_success_rate' => 0,
                'distribution' => [],
            ];
        }

        // Credits distribution
        $distribution = [
            '0-15' => 0,
            '16-20' => 0,
            '21-25' => 0,
            '26-30' => 0,
        ];

        foreach ($results as $result) {
            $credits = $result->acquired_credits;
            if ($credits <= 15) {
                $distribution['0-15']++;
            } elseif ($credits <= 20) {
                $distribution['16-20']++;
            } elseif ($credits <= 25) {
                $distribution['21-25']++;
            } else {
                $distribution['26-30']++;
            }
        }

        return [
            'total_students' => $results->count(),
            'avg_credits_acquired' => round($results->avg('acquired_credits'), 2),
            'avg_success_rate' => round($results->avg('success_rate'), 2),
            'distribution' => $distribution,
        ];
    }
}
