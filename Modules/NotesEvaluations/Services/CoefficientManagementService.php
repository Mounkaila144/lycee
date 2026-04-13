<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\NotesEvaluations\Entities\CoefficientHistory;
use Modules\NotesEvaluations\Entities\CoefficientTemplate;
use Modules\NotesEvaluations\Entities\CreditsHistory;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\NotesEvaluations\Exceptions\CoefficientLockedException;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;

class CoefficientManagementService
{
    public function __construct(
        protected ModuleAverageService $moduleAverageService,
        protected SemesterAverageService $semesterAverageService
    ) {}

    /**
     * Update evaluation coefficient
     */
    public function updateCoefficient(
        ModuleEvaluationConfig $evaluation,
        float $newCoefficient,
        ?string $reason = null
    ): void {
        $oldCoefficient = $evaluation->coefficient;

        // Check if modification is allowed
        if (! $this->canModifyCoefficient($evaluation)) {
            throw new CoefficientLockedException(
                "Coefficient verrouillé (notes publiées). Demande d'approbation requise."
            );
        }

        DB::beginTransaction();
        try {
            // Update coefficient
            $evaluation->update(['coefficient' => $newCoefficient]);

            // Create history entry
            CoefficientHistory::create([
                'evaluation_id' => $evaluation->id,
                'old_coefficient' => $oldCoefficient,
                'new_coefficient' => $newCoefficient,
                'changed_by' => auth()->id(),
                'reason' => $reason,
                'changed_at' => now(),
            ]);

            // Recalculate module averages
            $this->moduleAverageService->recalculateForModule(
                $evaluation->module_id,
                $evaluation->semester_id
            );

            // Invalidate cache
            Cache::flush();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Check if coefficient can be modified
     */
    public function canModifyCoefficient(ModuleEvaluationConfig $evaluation): bool
    {
        // Check if there are published grades
        $hasPublishedGrades = Grade::where('evaluation_id', $evaluation->id)
            ->where('status', 'Published')
            ->exists();

        if (! $hasPublishedGrades) {
            return true;
        }

        // Check if user has special permission
        return auth()->user()?->hasRole('Responsable Académique') ?? false;
    }

    /**
     * Update module credits ECTS
     */
    public function updateCredits(
        Module $module,
        int $newCredits,
        ?string $reason = null
    ): void {
        $oldCredits = $module->credits_ects;

        DB::beginTransaction();
        try {
            $module->update(['credits_ects' => $newCredits]);

            // Create history entry
            CreditsHistory::create([
                'module_id' => $module->id,
                'old_credits' => $oldCredits,
                'new_credits' => $newCredits,
                'changed_by' => auth()->id(),
                'reason' => $reason,
                'changed_at' => now(),
            ]);

            // Recalculate semester averages for all semesters with this module
            $semesterIds = $module->semesterAssignments()->pluck('semester_id')->unique();

            foreach ($semesterIds as $semesterId) {
                $this->semesterAverageService->recalculateForSemester($semesterId);
            }

            Cache::flush();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Apply coefficient template to a module
     */
    public function applyTemplate(Module $module, int $templateId, int $semesterId): void
    {
        $template = CoefficientTemplate::findOrFail($templateId);

        DB::beginTransaction();
        try {
            // Check if there are grades for this module
            $hasGrades = Grade::whereHas('evaluation', function ($q) use ($module, $semesterId) {
                $q->where('module_id', $module->id)
                    ->where('semester_id', $semesterId);
            })->exists();

            if ($hasGrades) {
                throw new \Exception("Impossible d'appliquer le template: des notes ont déjà été saisies");
            }

            // Delete existing evaluations
            ModuleEvaluationConfig::where('module_id', $module->id)
                ->where('semester_id', $semesterId)
                ->delete();

            // Create new evaluations from template
            $order = 1;
            foreach ($template->evaluations as $evalTemplate) {
                ModuleEvaluationConfig::create([
                    'module_id' => $module->id,
                    'semester_id' => $semesterId,
                    'name' => $evalTemplate['type'],
                    'type' => $evalTemplate['type'],
                    'coefficient' => $evalTemplate['coefficient'],
                    'max_score' => $evalTemplate['max_score'] ?? 20,
                    'order' => $order++,
                    'status' => 'Draft',
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Simulate impact of coefficient change
     */
    public function simulateImpact(ModuleEvaluationConfig $evaluation, float $newCoefficient): array
    {
        // Get a sample of grades
        $grades = Grade::where('evaluation_id', $evaluation->id)
            ->whereNotNull('score')
            ->inRandomOrder()
            ->limit(5)
            ->get();

        $impacts = [];
        $originalCoef = $evaluation->coefficient;

        foreach ($grades as $grade) {
            // Calculate old average
            $oldAverage = $this->moduleAverageService->calculate(
                $grade->student_id,
                $evaluation->module_id,
                $evaluation->semester_id
            );

            // Temporarily change coefficient for simulation
            $evaluation->coefficient = $newCoefficient;

            // Calculate new average (simulated)
            $newAverage = $this->calculateSimulatedAverage(
                $grade->student_id,
                $evaluation->module_id,
                $evaluation->semester_id,
                $evaluation->id,
                $newCoefficient
            );

            // Restore original coefficient
            $evaluation->coefficient = $originalCoef;

            $impacts[] = [
                'student_id' => $grade->student_id,
                'student_name' => $grade->student->full_name ?? $grade->student->firstname.' '.$grade->student->lastname,
                'old_average' => $oldAverage,
                'new_average' => $newAverage,
                'diff' => $newAverage !== null && $oldAverage !== null
                    ? round($newAverage - $oldAverage, 2)
                    : null,
            ];
        }

        return [
            'affected_students_count' => Grade::where('evaluation_id', $evaluation->id)->distinct('student_id')->count(),
            'sample_impacts' => $impacts,
        ];
    }

    /**
     * Calculate simulated average with modified coefficient
     */
    protected function calculateSimulatedAverage(
        int $studentId,
        int $moduleId,
        int $semesterId,
        int $modifiedEvaluationId,
        float $newCoefficient
    ): ?float {
        $evaluations = ModuleEvaluationConfig::where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->get();

        $totalWeightedScore = 0;
        $totalCoefficient = 0;

        foreach ($evaluations as $evaluation) {
            $coefficient = $evaluation->id === $modifiedEvaluationId
                ? $newCoefficient
                : $evaluation->coefficient;

            $grade = Grade::where([
                'student_id' => $studentId,
                'evaluation_id' => $evaluation->id,
            ])->first();

            if (! $grade || $grade->is_absent) {
                continue;
            }

            $totalWeightedScore += $grade->score * $coefficient;
            $totalCoefficient += $coefficient;
        }

        return $totalCoefficient > 0
            ? round($totalWeightedScore / $totalCoefficient, 2)
            : null;
    }

    /**
     * Get coefficient history for an evaluation
     */
    public function getCoefficientHistory(int $evaluationId): array
    {
        return CoefficientHistory::where('evaluation_id', $evaluationId)
            ->with('changedByUser')
            ->orderBy('changed_at', 'desc')
            ->get()
            ->map(function ($history) {
                return [
                    'id' => $history->id,
                    'old_coefficient' => $history->old_coefficient,
                    'new_coefficient' => $history->new_coefficient,
                    'difference' => $history->difference,
                    'changed_by' => $history->changedByUser->name ?? 'Unknown',
                    'reason' => $history->reason,
                    'changed_at' => $history->changed_at->toIso8601String(),
                ];
            })
            ->toArray();
    }

    /**
     * Get credits history for a module
     */
    public function getCreditsHistory(int $moduleId): array
    {
        return CreditsHistory::where('module_id', $moduleId)
            ->with('changedByUser')
            ->orderBy('changed_at', 'desc')
            ->get()
            ->map(function ($history) {
                return [
                    'id' => $history->id,
                    'old_credits' => $history->old_credits,
                    'new_credits' => $history->new_credits,
                    'difference' => $history->difference,
                    'changed_by' => $history->changedByUser->name ?? 'Unknown',
                    'reason' => $history->reason,
                    'changed_at' => $history->changed_at->toIso8601String(),
                ];
            })
            ->toArray();
    }

    /**
     * Get total coefficients for a module in a semester
     */
    public function getTotalCoefficients(int $moduleId, int $semesterId): float
    {
        return ModuleEvaluationConfig::where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->sum('coefficient');
    }

    /**
     * Get all coefficient templates
     */
    public function getTemplates(): array
    {
        return CoefficientTemplate::orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get()
            ->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'description' => $template->description,
                    'evaluations' => $template->evaluations,
                    'is_system' => $template->is_system,
                    'total_coefficient' => $template->total_coefficient,
                    'evaluation_count' => $template->evaluation_count,
                ];
            })
            ->toArray();
    }

    /**
     * Create custom template
     */
    public function createTemplate(string $name, string $description, array $evaluations): CoefficientTemplate
    {
        return CoefficientTemplate::create([
            'name' => $name,
            'description' => $description,
            'evaluations' => $evaluations,
            'is_system' => false,
        ]);
    }
}
