<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Entities\GradeValidation;
use Modules\NotesEvaluations\Http\Requests\ValidateGradesRequest;
use Modules\NotesEvaluations\Http\Resources\GradeValidationResource;
use Modules\NotesEvaluations\Services\GradeAuditService;
use Modules\NotesEvaluations\Services\GradeValidationService;
use Modules\StructureAcademique\Entities\Module;

class GradeValidationController extends Controller
{
    public function __construct(
        private GradeValidationService $validationService,
        private GradeAuditService $auditService
    ) {}

    /**
     * List grade validations
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $moduleId = $request->query('module_id');
        $academicYearId = $request->query('academic_year_id');

        $validations = GradeValidation::with(['module', 'evaluation', 'submitter', 'validator'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($moduleId, fn ($q) => $q->where('module_id', $moduleId))
            ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->orderByDesc('submitted_at')
            ->paginate($request->query('per_page', 15));

        return response()->json([
            'data' => GradeValidationResource::collection($validations->items()),
            'meta' => [
                'current_page' => $validations->currentPage(),
                'last_page' => $validations->lastPage(),
                'per_page' => $validations->perPage(),
                'total' => $validations->total(),
            ],
        ]);
    }

    /**
     * Show validation details
     */
    public function show(int $validation): JsonResponse
    {
        $validation = GradeValidation::with(['module', 'evaluation', 'submitter', 'validator', 'academicYear', 'semester'])
            ->findOrFail($validation);

        return response()->json([
            'data' => new GradeValidationResource($validation),
        ]);
    }

    /**
     * Approve validation
     */
    public function validate(ValidateGradesRequest $request, int $validation): JsonResponse
    {
        $validation = GradeValidation::findOrFail($validation);
        $validator = $request->user();
        $notes = $request->input('notes');

        try {
            $this->validationService->validateGrades($validation, $validator, 'Approved', $notes);

            return response()->json([
                'message' => 'Notes validées avec succès.',
                'data' => new GradeValidationResource($validation->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject validation
     */
    public function reject(ValidateGradesRequest $request, int $validation): JsonResponse
    {
        $validation = GradeValidation::findOrFail($validation);
        $validator = $request->user();
        $reason = $request->input('reason');

        if (empty($reason)) {
            return response()->json([
                'message' => 'Le motif de rejet est obligatoire.',
            ], 422);
        }

        try {
            $this->validationService->validateGrades($validation, $validator, 'Rejected', $reason);

            return response()->json([
                'message' => 'Notes rejetées.',
                'data' => new GradeValidationResource($validation->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Publish validation
     */
    public function publish(Request $request, int $validation): JsonResponse
    {
        $validation = GradeValidation::findOrFail($validation);
        $scheduledAt = $request->input('scheduled_at');

        try {
            $publishAt = $scheduledAt ? new \DateTime($scheduledAt) : null;
            $this->validationService->publishGrades($validation, $publishAt);

            $message = $publishAt
                ? "Publication programmée pour le {$publishAt->format('d/m/Y H:i')}."
                : 'Notes publiées avec succès.';

            return response()->json([
                'message' => $message,
                'data' => new GradeValidationResource($validation->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Bulk publish validations
     */
    public function bulkPublish(Request $request): JsonResponse
    {
        $request->validate([
            'validation_ids' => ['required', 'array', 'min:1'],
            'validation_ids.*' => ['integer', 'exists:grade_validations,id'],
        ]);

        $results = $this->validationService->bulkPublish(
            $request->input('validation_ids'),
            $request->user()
        );

        return response()->json([
            'message' => "{$results['published']} validation(s) publiée(s).",
            'data' => $results,
        ]);
    }

    /**
     * Get validation statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $academicYearId = $request->query('academic_year_id');

        $stats = $this->validationService->getValidationStatistics($academicYearId);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Export audit trail for a module
     */
    public function auditTrail(Request $request, int $module)
    {
        $module = Module::findOrFail($module);
        $filename = "audit_trail_{$module->code}_".now()->format('Ymd').'.xlsx';

        return $this->auditService->exportModuleAuditTrail($module->id, $filename);
    }
}
