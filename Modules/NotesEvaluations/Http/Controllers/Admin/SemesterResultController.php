<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Entities\SemesterResult;
use Modules\NotesEvaluations\Http\Resources\SemesterResultResource;
use Modules\NotesEvaluations\Services\EliminatoryRulesService;
use Modules\NotesEvaluations\Services\SemesterAverageService;

class SemesterResultController extends Controller
{
    public function __construct(
        protected SemesterAverageService $semesterService,
        protected EliminatoryRulesService $eliminatoryService
    ) {}

    /**
     * Get semester results for all students
     */
    public function index(Request $request, int $semesterId): JsonResponse
    {
        $results = SemesterResult::where('semester_id', $semesterId)
            ->with(['student', 'semester.academicYear'])
            ->when($request->search, function ($q, $search) {
                $q->whereHas('student', function ($sq) use ($search) {
                    $sq->where('matricule', 'like', "%{$search}%")
                        ->orWhere('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%");
                });
            })
            ->when($request->validated !== null, function ($q) use ($request) {
                $q->where('is_validated', filter_var($request->validated, FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->blocked_by_eliminatory !== null, function ($q) use ($request) {
                $q->where('validation_blocked_by_eliminatory', filter_var($request->blocked_by_eliminatory, FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->global_status, function ($q, $status) {
                $q->where('global_status', $status);
            })
            ->when($request->can_progress !== null, function ($q) use ($request) {
                $q->where('can_progress_next_year', filter_var($request->can_progress, FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->sort_by === 'rank', function ($q) {
                $q->orderBy('rank', 'asc');
            }, function ($q) {
                $q->orderBy('average', 'desc');
            })
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => SemesterResultResource::collection($results),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
            ],
        ]);
    }

    /**
     * Get students grouped by global status
     */
    public function studentsByStatus(int $semesterId, Request $request): JsonResponse
    {
        $status = $request->query('status');

        if (! $status || ! in_array($status, ['validated', 'partially_validated', 'to_retake', 'deferred'])) {
            return response()->json([
                'message' => 'Statut invalide. Utilisez: validated, partially_validated, to_retake, deferred',
            ], 400);
        }

        $results = $this->semesterService->getStudentsByStatus($semesterId, $status);

        return response()->json([
            'data' => SemesterResultResource::collection($results),
            'count' => $results->count(),
            'status' => $status,
        ]);
    }

    /**
     * Get semester statistics
     */
    public function statistics(int $semesterId): JsonResponse
    {
        $statistics = $this->semesterService->getSemesterStatistics($semesterId);
        $eliminatoryStats = $this->eliminatoryService->getEliminatoryStatistics($semesterId);

        return response()->json([
            'data' => array_merge($statistics, ['eliminatory' => $eliminatoryStats]),
        ]);
    }

    /**
     * Recalculate all semester averages
     */
    public function recalculate(int $semesterId): JsonResponse
    {
        $this->semesterService->recalculateForSemester($semesterId);

        return response()->json([
            'message' => 'Recalcul des moyennes de semestre lancé avec succès.',
        ]);
    }

    /**
     * Publish semester results
     */
    public function publish(int $semesterId): JsonResponse
    {
        $count = $this->semesterService->publishResults($semesterId);

        return response()->json([
            'message' => "Résultats publiés avec succès. {$count} résultats publiés.",
            'published_count' => $count,
        ]);
    }

    /**
     * Get student semester result
     */
    public function show(int $studentId, int $semesterId): JsonResponse
    {
        $result = SemesterResult::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->with(['student', 'semester.academicYear', 'ectsAllocations.module'])
            ->firstOrFail();

        return response()->json([
            'data' => new SemesterResultResource($result),
        ]);
    }

    /**
     * Get students blocked by eliminatory modules
     */
    public function blockedByEliminatory(int $semesterId): JsonResponse
    {
        $students = $this->eliminatoryService->getStudentsBlockedByEliminatory($semesterId);

        return response()->json([
            'data' => $students,
            'count' => $students->count(),
        ]);
    }
}
