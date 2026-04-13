<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Entities\RetakeEnrollment;
use Modules\NotesEvaluations\Entities\RetakeGrade;
use Modules\NotesEvaluations\Http\Resources\RetakeGradeResource;
use Modules\NotesEvaluations\Services\RetakeGradeService;

class RetakeGradeValidationController extends Controller
{
    public function __construct(
        protected RetakeGradeService $retakeGradeService
    ) {}

    /**
     * Get pending retake grades for validation
     * GET /api/admin/semesters/{semester}/retake-grades/pending
     */
    public function pending(Request $request, int $semesterId): JsonResponse
    {
        $moduleId = $request->query('module_id');

        $query = RetakeGrade::whereHas('retakeEnrollment', function ($q) use ($semesterId, $moduleId) {
            $q->where('semester_id', $semesterId);
            if ($moduleId) {
                $q->where('module_id', $moduleId);
            }
        })
            ->where('status', 'submitted')
            ->with(['retakeEnrollment.student', 'retakeEnrollment.module']);

        $grades = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => RetakeGradeResource::collection($grades),
            'meta' => [
                'current_page' => $grades->currentPage(),
                'last_page' => $grades->lastPage(),
                'per_page' => $grades->perPage(),
                'total' => $grades->total(),
            ],
        ]);
    }

    /**
     * Get modules with pending retake grades
     * GET /api/admin/semesters/{semester}/retake-grades/modules-pending
     */
    public function modulesPending(int $semesterId): JsonResponse
    {
        $modules = RetakeEnrollment::where('semester_id', $semesterId)
            ->whereHas('retakeGrade', fn ($q) => $q->where('status', 'submitted'))
            ->with('module')
            ->get()
            ->groupBy('module_id')
            ->map(function ($enrollments) {
                $module = $enrollments->first()->module;

                return [
                    'module_id' => $module->id,
                    'module_code' => $module->code,
                    'module_name' => $module->name,
                    'pending_count' => $enrollments->count(),
                ];
            })
            ->values();

        return response()->json([
            'data' => $modules,
        ]);
    }

    /**
     * Validate retake grades for a module
     * POST /api/admin/modules/{module}/semesters/{semester}/retake-grades/validate
     */
    public function validateGrades(int $moduleId, int $semesterId): JsonResponse
    {
        $validated = $this->retakeGradeService->validateGrades($moduleId, $semesterId);

        return response()->json([
            'message' => "{$validated} notes de rattrapage validées.",
            'data' => [
                'validated' => $validated,
            ],
        ]);
    }

    /**
     * Publish retake grades for a module
     * POST /api/admin/modules/{module}/semesters/{semester}/retake-grades/publish
     */
    public function publishGrades(int $moduleId, int $semesterId): JsonResponse
    {
        $result = $this->retakeGradeService->publishGrades($moduleId, $semesterId);

        if (! empty($result['errors'])) {
            return response()->json([
                'message' => "Publication partielle: {$result['published']} notes publiées.",
                'data' => $result,
            ], 207);
        }

        return response()->json([
            'message' => "{$result['published']} notes de rattrapage publiées.",
            'data' => $result,
        ]);
    }

    /**
     * Get retake grades statistics for a module
     * GET /api/admin/modules/{module}/semesters/{semester}/retake-grades/statistics
     */
    public function statistics(int $moduleId, int $semesterId): JsonResponse
    {
        $stats = $this->retakeGradeService->getStatistics($moduleId, $semesterId);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Bulk validate retake grades
     * POST /api/admin/semesters/{semester}/retake-grades/bulk-validate
     */
    public function bulkValidate(Request $request, int $semesterId): JsonResponse
    {
        $gradeIds = $request->input('grade_ids', []);

        if (empty($gradeIds)) {
            return response()->json([
                'message' => 'Aucune note sélectionnée.',
            ], 422);
        }

        $validated = 0;

        foreach ($gradeIds as $gradeId) {
            $grade = RetakeGrade::find($gradeId);
            if ($grade && $grade->canBeValidated()) {
                $grade->validate();
                $validated++;
            }
        }

        return response()->json([
            'message' => "{$validated} notes validées.",
            'data' => [
                'validated' => $validated,
            ],
        ]);
    }

    /**
     * Reject a retake grade (send back to teacher)
     * POST /api/admin/retake-grades/{retakeGrade}/reject
     */
    public function reject(Request $request, RetakeGrade $retakeGrade): JsonResponse
    {
        if (! $retakeGrade->isSubmitted()) {
            return response()->json([
                'message' => 'Cette note ne peut pas être rejetée.',
            ], 422);
        }

        $retakeGrade->update([
            'status' => 'draft',
            'submitted_at' => null,
        ]);

        return response()->json([
            'message' => 'Note rejetée et renvoyée à l\'enseignant.',
            'data' => new RetakeGradeResource($retakeGrade->fresh()),
        ]);
    }
}
