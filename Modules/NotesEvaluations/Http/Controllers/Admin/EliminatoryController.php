<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Http\Requests\UpdateEliminatoryThresholdRequest;
use Modules\NotesEvaluations\Services\EliminatoryRulesService;
use Modules\StructureAcademique\Entities\Module;

class EliminatoryController extends Controller
{
    public function __construct(
        protected EliminatoryRulesService $eliminatoryService
    ) {}

    /**
     * Get eliminatory modules for a semester
     */
    public function index(int $semesterId): JsonResponse
    {
        $modules = $this->eliminatoryService->getEliminatoryModules($semesterId);

        return response()->json([
            'data' => $modules,
        ]);
    }

    /**
     * Toggle eliminatory status for a module
     */
    public function toggle(int $moduleId): JsonResponse
    {
        $module = Module::findOrFail($moduleId);

        $module->update([
            'is_eliminatory' => ! $module->is_eliminatory,
        ]);

        return response()->json([
            'message' => $module->is_eliminatory
                ? 'Module marqué comme éliminatoire.'
                : 'Module marqué comme non éliminatoire.',
            'data' => [
                'id' => $module->id,
                'is_eliminatory' => $module->is_eliminatory,
            ],
        ]);
    }

    /**
     * Update eliminatory threshold for a module
     */
    public function updateThreshold(UpdateEliminatoryThresholdRequest $request, int $moduleId): JsonResponse
    {
        $module = Module::findOrFail($moduleId);

        $module->update([
            'eliminatory_threshold' => $request->eliminatory_threshold,
        ]);

        return response()->json([
            'message' => 'Seuil éliminatoire mis à jour avec succès.',
            'data' => [
                'id' => $module->id,
                'eliminatory_threshold' => $module->eliminatory_threshold,
            ],
        ]);
    }

    /**
     * Get eliminatory status for a student
     */
    public function studentStatus(int $studentId, int $moduleId, int $semesterId): JsonResponse
    {
        $status = $this->eliminatoryService->checkEliminatoryStatus(
            $studentId,
            $moduleId,
            $semesterId
        );

        return response()->json([
            'data' => [
                'status' => $status,
            ],
        ]);
    }

    /**
     * Get failed eliminatory modules for a student
     */
    public function studentFailedModules(int $studentId, int $semesterId): JsonResponse
    {
        $failedModules = $this->eliminatoryService->getFailedEliminatoryModules(
            $studentId,
            $semesterId
        );

        return response()->json([
            'data' => $failedModules,
        ]);
    }

    /**
     * Get students blocked by eliminatory modules
     */
    public function blockedStudents(int $semesterId): JsonResponse
    {
        $students = $this->eliminatoryService->getStudentsBlockedByEliminatory($semesterId);

        return response()->json([
            'data' => $students,
            'count' => $students->count(),
        ]);
    }

    /**
     * Get eliminatory statistics
     */
    public function statistics(int $semesterId): JsonResponse
    {
        $statistics = $this->eliminatoryService->getEliminatoryStatistics($semesterId);

        return response()->json([
            'data' => $statistics,
        ]);
    }
}
