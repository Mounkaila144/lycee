<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\NotesEvaluations\Entities\CompensationLog;
use Modules\NotesEvaluations\Entities\EctsAllocation;
use Modules\NotesEvaluations\Entities\GradeConfig;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Entities\SemesterResult;
use Modules\StructureAcademique\Entities\Module;

class CompensationRulesService
{
    /**
     * Apply compensation rules for a student in a specific semester
     *
     * @return array{compensated: Collection, skipped: Collection, reason: ?string}
     */
    public function applyCompensation(int $studentId, int $semesterId): array
    {
        $config = GradeConfig::getConfig();

        if (! $config->compensation_enabled) {
            return [
                'compensated' => collect(),
                'skipped' => collect(),
                'reason' => 'Compensation désactivée pour ce tenant',
            ];
        }

        $semesterResult = SemesterResult::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->first();

        if (! $semesterResult) {
            return [
                'compensated' => collect(),
                'skipped' => collect(),
                'reason' => 'Aucun résultat semestre trouvé',
            ];
        }

        if ($semesterResult->average < $config->min_semester_average) {
            return [
                'compensated' => collect(),
                'skipped' => collect(),
                'reason' => sprintf(
                    'Moyenne semestre (%.2f) inférieure au seuil de compensation (%.2f)',
                    $semesterResult->average,
                    $config->min_semester_average
                ),
            ];
        }

        // Get compensable modules
        $compensableModules = $this->getCompensableModules($studentId, $semesterId, $config);

        if ($compensableModules->isEmpty()) {
            return [
                'compensated' => collect(),
                'skipped' => collect(),
                'reason' => 'Aucun module compensable',
            ];
        }

        // Apply max limit
        $maxCompensated = $config->max_compensated_modules ?? PHP_INT_MAX;
        $toCompensate = $compensableModules->take($maxCompensated);
        $skipped = $compensableModules->skip($maxCompensated);

        $compensated = collect();

        DB::beginTransaction();
        try {
            foreach ($toCompensate as $moduleGrade) {
                $this->compensateModule($moduleGrade, $semesterResult, $config);
                $compensated->push($moduleGrade->fresh());
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'compensated' => $compensated,
            'skipped' => $skipped,
            'reason' => null,
        ];
    }

    /**
     * Compensate a single module
     */
    private function compensateModule(
        ModuleGrade $moduleGrade,
        SemesterResult $semesterResult,
        GradeConfig $config
    ): void {
        // Update module grade status
        $moduleGrade->update([
            'status' => 'Compensated',
            'compensation_applied_at' => now(),
        ]);

        // Allocate ECTS credits
        EctsAllocation::updateOrCreate(
            [
                'student_id' => $moduleGrade->student_id,
                'module_id' => $moduleGrade->module_id,
                'semester_id' => $moduleGrade->semester_id,
            ],
            [
                'credits_allocated' => $moduleGrade->module->credits_ects ?? 0,
                'allocation_type' => 'compensated',
                'allocated_at' => now(),
                'semester_result_id' => $semesterResult->id,
            ]
        );

        // Create compensation log
        CompensationLog::create([
            'student_id' => $moduleGrade->student_id,
            'module_id' => $moduleGrade->module_id,
            'semester_id' => $moduleGrade->semester_id,
            'module_average' => $moduleGrade->average,
            'semester_average' => $semesterResult->average,
            'compensation_reason' => sprintf(
                'Moyenne semestre (%.2f) ≥ seuil compensation (%.2f)',
                $semesterResult->average,
                $config->min_semester_average
            ),
            'applied_at' => now(),
            'applied_by' => Auth::id(),
        ]);
    }

    /**
     * Get compensable modules for a student, sorted by priority
     */
    public function getCompensableModules(
        int $studentId,
        int $semesterId,
        ?GradeConfig $config = null
    ): Collection {
        $config = $config ?? GradeConfig::getConfig();

        $minGrade = $config->min_compensable_grade ?? 0;
        $validationThreshold = $config->min_module_average ?? 10.00;

        return ModuleGrade::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->whereHas('module', function ($q) use ($config) {
                $q->where('is_eliminatory', false);

                if (! $config->allow_professional_module_compensation) {
                    $q->where('is_professional', false);
                }
            })
            ->whereNotNull('average')
            ->where('average', '>=', $minGrade)
            ->where('average', '<', $validationThreshold)
            ->where('status', '!=', 'Compensated')
            ->with('module')
            ->get()
            ->sortBy([
                ['average', 'desc'], // Higher average first (closest to 10)
                fn ($a, $b) => ($b->module->credits_ects ?? 0) <=> ($a->module->credits_ects ?? 0), // More ECTS first
            ])
            ->values();
    }

    /**
     * Check if a specific module can be compensated for a student
     *
     * @return array{can_compensate: bool, reason: ?string}
     */
    public function canBeCompensated(int $studentId, int $moduleId, int $semesterId): array
    {
        $config = GradeConfig::getConfig();

        if (! $config->compensation_enabled) {
            return ['can_compensate' => false, 'reason' => 'Compensation désactivée'];
        }

        $module = Module::find($moduleId);
        if (! $module) {
            return ['can_compensate' => false, 'reason' => 'Module non trouvé'];
        }

        $moduleGrade = ModuleGrade::where([
            'student_id' => $studentId,
            'module_id' => $moduleId,
            'semester_id' => $semesterId,
        ])->first();

        if (! $moduleGrade || $moduleGrade->average === null) {
            return ['can_compensate' => false, 'reason' => 'Pas de note pour ce module'];
        }

        // Already compensated
        if ($moduleGrade->status === 'Compensated') {
            return ['can_compensate' => false, 'reason' => 'Module déjà compensé'];
        }

        // Module is eliminatory
        if ($module->is_eliminatory) {
            return ['can_compensate' => false, 'reason' => 'Module éliminatoire - non compensable'];
        }

        // Professional module check
        if ($module->is_professional && ! $config->allow_professional_module_compensation) {
            return ['can_compensate' => false, 'reason' => 'Module professionnel - non compensable selon configuration'];
        }

        // Grade already valid
        $validationThreshold = $config->min_module_average ?? 10.00;
        if ($moduleGrade->average >= $validationThreshold) {
            return ['can_compensate' => false, 'reason' => 'Module déjà validé'];
        }

        // Grade too low for compensation
        $minGrade = $config->min_compensable_grade ?? 0;
        if ($moduleGrade->average < $minGrade) {
            return [
                'can_compensate' => false,
                'reason' => sprintf(
                    'Note (%.2f) inférieure au seuil minimal compensable (%.2f)',
                    $moduleGrade->average,
                    $minGrade
                ),
            ];
        }

        // Check semester average
        $semesterResult = SemesterResult::where([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
        ])->first();

        if (! $semesterResult || $semesterResult->average < $config->min_semester_average) {
            return [
                'can_compensate' => false,
                'reason' => sprintf(
                    'Moyenne semestre (%.2f) inférieure au seuil de compensation (%.2f)',
                    $semesterResult?->average ?? 0,
                    $config->min_semester_average
                ),
            ];
        }

        return ['can_compensate' => true, 'reason' => null];
    }

    /**
     * Simulate compensation impact for an entire semester
     *
     * @return array{students_impacted: int, modules_compensated: int, credits_allocated: int, details: array}
     */
    public function simulateCompensation(int $semesterId): array
    {
        $config = GradeConfig::getConfig();

        if (! $config->compensation_enabled) {
            return [
                'students_impacted' => 0,
                'modules_compensated' => 0,
                'credits_allocated' => 0,
                'details' => [],
                'reason' => 'Compensation désactivée',
            ];
        }

        $semesterResults = SemesterResult::where('semester_id', $semesterId)
            ->where('average', '>=', $config->min_semester_average)
            ->get();

        $totalCompensated = 0;
        $totalCredits = 0;
        $studentsImpacted = 0;
        $details = [];

        foreach ($semesterResults as $semesterResult) {
            $compensableModules = $this->getCompensableModules(
                $semesterResult->student_id,
                $semesterId,
                $config
            );

            $maxCompensated = $config->max_compensated_modules ?? PHP_INT_MAX;
            $toCompensate = $compensableModules->take($maxCompensated);

            if ($toCompensate->isNotEmpty()) {
                $studentsImpacted++;
                $moduleCount = $toCompensate->count();
                $credits = $toCompensate->sum(fn ($mg) => $mg->module->credits_ects ?? 0);

                $totalCompensated += $moduleCount;
                $totalCredits += $credits;

                $details[] = [
                    'student_id' => $semesterResult->student_id,
                    'semester_average' => $semesterResult->average,
                    'modules_count' => $moduleCount,
                    'credits' => $credits,
                    'modules' => $toCompensate->map(fn ($mg) => [
                        'module_id' => $mg->module_id,
                        'module_code' => $mg->module->code ?? 'N/A',
                        'module_name' => $mg->module->name ?? 'N/A',
                        'average' => $mg->average,
                        'credits_ects' => $mg->module->credits_ects ?? 0,
                    ])->toArray(),
                ];
            }
        }

        return [
            'students_impacted' => $studentsImpacted,
            'modules_compensated' => $totalCompensated,
            'credits_allocated' => $totalCredits,
            'details' => $details,
        ];
    }

    /**
     * Get compensation history for a student
     */
    public function getStudentCompensationHistory(int $studentId, ?int $semesterId = null): Collection
    {
        $query = CompensationLog::where('student_id', $studentId)
            ->with(['module', 'semester', 'appliedByUser']);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        return $query->orderByDesc('applied_at')->get();
    }

    /**
     * Revoke compensation for a module (admin function)
     */
    public function revokeCompensation(int $studentId, int $moduleId, int $semesterId): bool
    {
        $moduleGrade = ModuleGrade::where([
            'student_id' => $studentId,
            'module_id' => $moduleId,
            'semester_id' => $semesterId,
            'status' => 'Compensated',
        ])->first();

        if (! $moduleGrade) {
            return false;
        }

        DB::beginTransaction();
        try {
            // Revert module grade status
            $moduleGrade->update([
                'status' => 'Final',
                'compensation_applied_at' => null,
            ]);

            // Remove ECTS allocation
            EctsAllocation::where([
                'student_id' => $studentId,
                'module_id' => $moduleId,
                'allocation_type' => 'compensated',
            ])->delete();

            // Soft delete compensation log
            CompensationLog::where([
                'student_id' => $studentId,
                'module_id' => $moduleId,
                'semester_id' => $semesterId,
            ])->delete();

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
