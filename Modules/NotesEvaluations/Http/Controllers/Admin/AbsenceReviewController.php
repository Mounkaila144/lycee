<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Entities\AbsenceJustification;
use Modules\NotesEvaluations\Entities\GradeAbsence;
use Modules\NotesEvaluations\Services\AbsencePolicyService;

class AbsenceReviewController extends Controller
{
    public function __construct(
        private AbsencePolicyService $absenceService
    ) {}

    /**
     * List all pending justifications
     */
    public function index(Request $request): JsonResponse
    {
        $query = AbsenceJustification::with(['student', 'evaluation.module'])
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->module_id, fn ($q, $moduleId) => $q->whereHas('evaluation', fn ($eq) => $eq->where('module_id', $moduleId)))
            ->orderBy('submitted_at', 'desc');

        $justifications = $request->has('per_page')
            ? $query->paginate($request->per_page)
            : $query->get();

        $transform = fn ($j) => [
            'id' => $j->id,
            'student' => [
                'id' => $j->student->id,
                'firstname' => $j->student->firstname,
                'lastname' => $j->student->lastname,
                'matricule' => $j->student->matricule,
            ],
            'evaluation' => [
                'id' => $j->evaluation->id,
                'name' => $j->evaluation->name,
                'module_name' => $j->evaluation->module->name ?? 'Module',
            ],
            'status' => $j->status,
            'submitted_at' => $j->submitted_at->toIso8601String(),
            'original_filename' => $j->original_filename,
            'reviewed_at' => $j->reviewed_at?->toIso8601String(),
            'admin_comment' => $j->admin_comment,
        ];

        if ($request->has('per_page')) {
            return response()->json([
                'data' => $justifications->getCollection()->map($transform),
                'meta' => [
                    'current_page' => $justifications->currentPage(),
                    'last_page' => $justifications->lastPage(),
                    'per_page' => $justifications->perPage(),
                    'total' => $justifications->total(),
                ],
            ]);
        }

        return response()->json([
            'data' => $justifications->map($transform),
        ]);
    }

    /**
     * Get justification details
     */
    public function show(AbsenceJustification $justification): JsonResponse
    {
        $justification->load(['student', 'evaluation.module', 'reviewer']);

        return response()->json([
            'data' => [
                'id' => $justification->id,
                'student' => [
                    'id' => $justification->student->id,
                    'firstname' => $justification->student->firstname,
                    'lastname' => $justification->student->lastname,
                    'matricule' => $justification->student->matricule,
                    'email' => $justification->student->email,
                ],
                'evaluation' => [
                    'id' => $justification->evaluation->id,
                    'name' => $justification->evaluation->name,
                    'module_name' => $justification->evaluation->module->name ?? 'Module',
                    'date' => $justification->evaluation->date?->toIso8601String(),
                ],
                'status' => $justification->status,
                'submitted_at' => $justification->submitted_at->toIso8601String(),
                'original_filename' => $justification->original_filename,
                'file_path' => $justification->file_path,
                'reviewed_at' => $justification->reviewed_at?->toIso8601String(),
                'reviewed_by' => $justification->reviewer ? [
                    'id' => $justification->reviewer->id,
                    'name' => $justification->reviewer->name,
                ] : null,
                'admin_comment' => $justification->admin_comment,
            ],
        ]);
    }

    /**
     * Download justification file
     */
    public function download(AbsenceJustification $justification): mixed
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('tenant');

        if (! $disk->exists($justification->file_path)) {
            return response()->json([
                'message' => 'Fichier non trouvé.',
            ], 404);
        }

        return $disk->download(
            $justification->file_path,
            $justification->original_filename
        );
    }

    /**
     * Approve a justification
     */
    public function approve(Request $request, AbsenceJustification $justification): JsonResponse
    {
        if ($justification->status !== 'pending') {
            return response()->json([
                'message' => 'Ce justificatif a déjà été traité.',
            ], 422);
        }

        $validated = $request->validate([
            'comment' => 'nullable|string|max:1000',
        ]);

        $this->absenceService->approveJustification(
            $justification,
            $request->user(),
            $validated['comment'] ?? null
        );

        return response()->json([
            'message' => 'Justificatif approuvé avec succès.',
        ]);
    }

    /**
     * Reject a justification
     */
    public function reject(Request $request, AbsenceJustification $justification): JsonResponse
    {
        if ($justification->status !== 'pending') {
            return response()->json([
                'message' => 'Ce justificatif a déjà été traité.',
            ], 422);
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $this->absenceService->rejectJustification(
            $justification,
            $request->user(),
            $validated['comment']
        );

        return response()->json([
            'message' => 'Justificatif rejeté.',
        ]);
    }

    /**
     * Get absence statistics summary
     */
    public function statistics(Request $request): JsonResponse
    {
        $query = GradeAbsence::query();

        if ($request->module_id) {
            $query->whereHas('grade.evaluation', fn ($q) => $q->where('module_id', $request->module_id));
        }

        if ($request->academic_year_id) {
            $query->whereHas('grade.evaluation', fn ($q) => $q->where('academic_year_id', $request->academic_year_id));
        }

        $absences = $query->get();

        $pendingJustifications = AbsenceJustification::where('status', 'pending')->count();

        return response()->json([
            'data' => [
                'total_absences' => $absences->count(),
                'unjustified' => $absences->where('absence_type', 'unjustified')->count(),
                'pending' => $absences->where('absence_type', 'pending')->count(),
                'justified' => $absences->where('absence_type', 'justified')->count(),
                'pending_justifications' => $pendingJustifications,
            ],
        ]);
    }

    /**
     * Bulk approve justifications
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'justification_ids' => 'required|array|min:1',
            'justification_ids.*' => 'required|integer|exists:tenant.absence_justifications,id',
            'comment' => 'nullable|string|max:1000',
        ]);

        $approved = 0;
        $errors = [];

        foreach ($validated['justification_ids'] as $id) {
            try {
                $justification = AbsenceJustification::on('tenant')->find($id);

                if ($justification && $justification->status === 'pending') {
                    $this->absenceService->approveJustification(
                        $justification,
                        $request->user(),
                        $validated['comment'] ?? null
                    );
                    $approved++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => "{$approved} justificatif(s) approuvé(s).",
            'approved_count' => $approved,
            'errors' => $errors,
        ]);
    }

    /**
     * Bulk reject justifications
     */
    public function bulkReject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'justification_ids' => 'required|array|min:1',
            'justification_ids.*' => 'required|integer|exists:tenant.absence_justifications,id',
            'comment' => 'required|string|max:1000',
        ]);

        $rejected = 0;
        $errors = [];

        foreach ($validated['justification_ids'] as $id) {
            try {
                $justification = AbsenceJustification::on('tenant')->find($id);

                if ($justification && $justification->status === 'pending') {
                    $this->absenceService->rejectJustification(
                        $justification,
                        $request->user(),
                        $validated['comment']
                    );
                    $rejected++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => "{$rejected} justificatif(s) rejeté(s).",
            'rejected_count' => $rejected,
            'errors' => $errors,
        ]);
    }
}
