<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Modules\NotesEvaluations\Exports\ModuleResultExport;
use Modules\NotesEvaluations\Http\Resources\ModuleGradeResource;
use Modules\NotesEvaluations\Http\Resources\ModuleResultResource;
use Modules\NotesEvaluations\Services\ModuleResultsService;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Semester;

class ModuleResultController extends Controller
{
    public function __construct(
        private ModuleResultsService $moduleResultsService
    ) {}

    /**
     * Get module results for a specific semester
     */
    public function show(int $module, int $semester): JsonResponse
    {
        $module = Module::findOrFail($module);
        $semester = Semester::findOrFail($semester);

        $result = $this->moduleResultsService->getResult($module->id, $semester->id);

        if (! $result) {
            return response()->json([
                'message' => 'Aucun résultat généré pour ce module.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'data' => new ModuleResultResource($result),
        ]);
    }

    /**
     * Generate or regenerate module results
     */
    public function generate(int $module, int $semester): JsonResponse
    {
        $module = Module::findOrFail($module);
        $semester = Semester::findOrFail($semester);

        $result = $this->moduleResultsService->generate($module->id, $semester->id);

        return response()->json([
            'message' => 'Résultats générés avec succès.',
            'data' => new ModuleResultResource($result),
        ]);
    }

    /**
     * Publish module results
     */
    public function publish(int $module, int $semester): JsonResponse
    {
        $module = Module::findOrFail($module);
        $semester = Semester::findOrFail($semester);

        $result = $this->moduleResultsService->publish($module->id, $semester->id);

        if (! $result) {
            return response()->json([
                'message' => 'Aucun résultat à publier.',
            ], 404);
        }

        return response()->json([
            'message' => 'Résultats publiés avec succès.',
            'data' => new ModuleResultResource($result->fresh()),
        ]);
    }

    /**
     * Get students grouped by status
     */
    public function studentsByStatus(int $module, int $semester, Request $request): JsonResponse
    {
        $module = Module::findOrFail($module);
        $semester = Semester::findOrFail($semester);

        $studentsByStatus = $this->moduleResultsService->getStudentsByStatus($module->id, $semester->id);

        $status = $request->query('status', 'all');

        $data = match ($status) {
            'validated' => ['validated' => ModuleGradeResource::collection($studentsByStatus['validated'])],
            'failed' => ['failed' => ModuleGradeResource::collection($studentsByStatus['failed'])],
            'absent' => ['absent' => ModuleGradeResource::collection($studentsByStatus['absent'])],
            default => [
                'validated' => ModuleGradeResource::collection($studentsByStatus['validated']),
                'failed' => ModuleGradeResource::collection($studentsByStatus['failed']),
                'absent' => ModuleGradeResource::collection($studentsByStatus['absent']),
                'counts' => [
                    'validated' => $studentsByStatus['validated']->count(),
                    'failed' => $studentsByStatus['failed']->count(),
                    'absent' => $studentsByStatus['absent']->count(),
                    'total' => $studentsByStatus['validated']->count()
                        + $studentsByStatus['failed']->count()
                        + $studentsByStatus['absent']->count(),
                ],
            ],
        };

        return response()->json(['data' => $data]);
    }

    /**
     * Export module results to Excel
     */
    public function export(int $module, int $semester, Request $request)
    {
        $module = Module::findOrFail($module);
        $semester = Semester::findOrFail($semester);

        $result = $this->moduleResultsService->getResult($module->id, $semester->id);
        $studentsByStatus = $this->moduleResultsService->getStudentsByStatus($module->id, $semester->id);

        $filename = "resultats_{$module->code}_{$semester->name}.xlsx";

        return Excel::download(
            new ModuleResultExport($result, $studentsByStatus, $module, $semester),
            $filename
        );
    }
}
