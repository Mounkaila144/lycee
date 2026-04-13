<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Entities\GradeCorrectionRequest;
use Modules\NotesEvaluations\Http\Resources\CorrectionRequestResource;
use Modules\NotesEvaluations\Services\GradeAuditService;

class CorrectionApprovalController extends Controller
{
    public function __construct(
        private GradeAuditService $auditService
    ) {}

    /**
     * List correction requests
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $requests = GradeCorrectionRequest::with([
            'grade.student',
            'grade.evaluation.module',
            'requester',
            'reviewer',
        ])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("FIELD(status, 'Pending', 'Approved', 'Rejected')")
            ->orderByDesc('created_at')
            ->paginate($request->query('per_page', 15));

        return response()->json([
            'data' => CorrectionRequestResource::collection($requests->items()),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
                'pending_count' => GradeCorrectionRequest::pending()->count(),
            ],
        ]);
    }

    /**
     * Show correction request details
     */
    public function show(int $id): JsonResponse
    {
        $correctionRequest = GradeCorrectionRequest::on('tenant')
            ->with(['grade.student', 'grade.evaluation.module', 'grade.history', 'requester', 'reviewer'])
            ->find($id);

        if (! $correctionRequest) {
            return response()->json([
                'message' => 'Demande de correction introuvable.',
            ], 404);
        }

        return response()->json([
            'data' => new CorrectionRequestResource($correctionRequest),
        ]);
    }

    /**
     * Approve correction request
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        // Load correction request explicitly from tenant database
        $correctionRequest = GradeCorrectionRequest::on('tenant')->find($id);

        if (! $correctionRequest) {
            return response()->json([
                'message' => 'Demande de correction introuvable.',
            ], 404);
        }

        $reviewer = $request->user();

        try {
            $this->auditService->approveCorrection(
                $correctionRequest,
                $reviewer,
                $request->input('comment')
            );

            return response()->json([
                'message' => 'Demande de correction approuvée.',
                'data' => new CorrectionRequestResource($correctionRequest->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject correction request
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'comment' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        // Load correction request explicitly from tenant database
        $correctionRequest = GradeCorrectionRequest::on('tenant')->find($id);

        if (! $correctionRequest) {
            return response()->json([
                'message' => 'Demande de correction introuvable.',
            ], 404);
        }

        $reviewer = $request->user();

        try {
            $this->auditService->rejectCorrection(
                $correctionRequest,
                $reviewer,
                $request->input('comment')
            );

            return response()->json([
                'message' => 'Demande de correction rejetée.',
                'data' => new CorrectionRequestResource($correctionRequest->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
