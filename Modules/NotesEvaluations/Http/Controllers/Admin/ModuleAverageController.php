<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Http\Resources\ModuleGradeResource;
use Modules\NotesEvaluations\Services\ModuleAverageService;

class ModuleAverageController extends Controller
{
    public function __construct(
        protected ModuleAverageService $averageService
    ) {}

    /**
     * Get module averages for a specific module and semester
     */
    public function index(Request $request, int $moduleId): JsonResponse
    {
        $semesterId = $request->input('semester_id');

        if (! $semesterId) {
            return response()->json([
                'message' => 'semester_id is required',
            ], 422);
        }

        $grades = ModuleGrade::where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->with(['student', 'module'])
            ->when($request->search, function ($q, $search) {
                $q->whereHas('student', function ($sq) use ($search) {
                    $sq->where('matricule', 'like', "%{$search}%")
                        ->orWhere('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%");
                });
            })
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => ModuleGradeResource::collection($grades),
            'meta' => [
                'current_page' => $grades->currentPage(),
                'last_page' => $grades->lastPage(),
                'per_page' => $grades->perPage(),
                'total' => $grades->total(),
            ],
        ]);
    }

    /**
     * Get statistics for a module
     */
    public function statistics(Request $request, int $moduleId): JsonResponse
    {
        $semesterId = $request->input('semester_id');

        if (! $semesterId) {
            return response()->json([
                'message' => 'semester_id is required',
            ], 422);
        }

        $statistics = $this->averageService->getModuleStatistics($moduleId, $semesterId);

        return response()->json([
            'data' => $statistics,
        ]);
    }

    /**
     * Force recalculate module averages
     */
    public function recalculate(Request $request, int $moduleId): JsonResponse
    {
        $semesterId = $request->input('semester_id');

        if (! $semesterId) {
            return response()->json([
                'message' => 'semester_id is required',
            ], 422);
        }

        $this->averageService->recalculateForModule($moduleId, $semesterId);

        return response()->json([
            'message' => 'Recalcul des moyennes lancé avec succès.',
        ]);
    }

    /**
     * Get student module grades for a semester
     */
    public function studentGrades(int $studentId, int $semesterId): JsonResponse
    {
        $grades = $this->averageService->getStudentModuleGrades($studentId, $semesterId);

        return response()->json([
            'data' => $grades,
        ]);
    }
}
