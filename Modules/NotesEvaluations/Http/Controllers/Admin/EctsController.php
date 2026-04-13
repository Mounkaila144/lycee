<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Entities\EctsAllocation;
use Modules\NotesEvaluations\Http\Requests\AllocateEquivalenceRequest;
use Modules\NotesEvaluations\Http\Resources\EctsAllocationResource;
use Modules\NotesEvaluations\Services\EctsCalculationService;

class EctsController extends Controller
{
    public function __construct(
        protected EctsCalculationService $ectsService
    ) {}

    /**
     * Get ECTS summary for a student
     */
    public function studentSummary(int $studentId): JsonResponse
    {
        $summary = $this->ectsService->getStudentEctsSummary($studentId);

        return response()->json([
            'data' => $summary,
        ]);
    }

    /**
     * Get ECTS allocations for a student in a semester
     */
    public function studentSemesterAllocations(int $studentId, int $semesterId): JsonResponse
    {
        $allocations = $this->ectsService->getStudentSemesterAllocations($studentId, $semesterId);

        return response()->json([
            'data' => $allocations,
        ]);
    }

    /**
     * Check if student can progress to next year
     */
    public function checkProgression(int $studentId, string $level): JsonResponse
    {
        $result = $this->ectsService->canProgressToNextYear($studentId, $level);

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Get semester ECTS statistics
     */
    public function semesterStatistics(int $semesterId): JsonResponse
    {
        $statistics = $this->ectsService->getSemesterEctsStatistics($semesterId);

        return response()->json([
            'data' => $statistics,
        ]);
    }

    /**
     * Allocate equivalence credits manually
     */
    public function allocateEquivalence(AllocateEquivalenceRequest $request, int $studentId): JsonResponse
    {
        $allocation = $this->ectsService->allocateEquivalence(
            $studentId,
            $request->module_id,
            $request->credits,
            $request->note
        );

        return response()->json([
            'message' => 'Équivalence attribuée avec succès.',
            'data' => new EctsAllocationResource($allocation),
        ], 201);
    }

    /**
     * Get all equivalences for a student
     */
    public function studentEquivalences(int $studentId): JsonResponse
    {
        $equivalences = EctsAllocation::where('student_id', $studentId)
            ->where('allocation_type', EctsAllocation::TYPE_EQUIVALENCE)
            ->with('module')
            ->get();

        return response()->json([
            'data' => EctsAllocationResource::collection($equivalences),
        ]);
    }

    /**
     * Force recalculate ECTS for a student
     */
    public function recalculate(int $studentId, int $semesterId): JsonResponse
    {
        $this->ectsService->allocateCredits($studentId, $semesterId);

        return response()->json([
            'message' => 'Crédits ECTS recalculés avec succès.',
        ]);
    }
}
