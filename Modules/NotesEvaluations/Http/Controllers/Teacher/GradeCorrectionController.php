<?php

namespace Modules\NotesEvaluations\Http\Controllers\Teacher;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\NotesEvaluations\Exports\GradeAuditExport;
use Modules\NotesEvaluations\Exports\GradeEvaluationHistoryExport;
use Modules\NotesEvaluations\Http\Requests\RequestCorrectionRequest;
use Modules\NotesEvaluations\Http\Resources\GradeHistoryResource;
use Modules\NotesEvaluations\Services\GradeAuditService;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\StructureAcademique\Entities\TeacherModuleAssignment;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GradeCorrectionController extends Controller
{
    public function __construct(
        private GradeAuditService $auditService
    ) {}

    /**
     * Get grade history
     */
    public function history(Request $request, int $grade): JsonResponse
    {
        $teacher = $request->user();

        // Load grade explicitly from tenant database to avoid route binding issues
        $grade = Grade::on('tenant')->find($grade);

        if (! $grade) {
            return response()->json([
                'message' => 'Note introuvable.',
            ], 404);
        }

        // Load evaluation using explicit query to handle tenant connection properly
        $evaluation = ModuleEvaluationConfig::on('tenant')->find($grade->evaluation_id);

        if (! $evaluation) {
            return response()->json([
                'message' => "L'évaluation associée à cette note est introuvable (evaluation_id: {$grade->evaluation_id}).",
            ], 404);
        }

        // Verify teacher is assigned to this module
        $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
            ->byModule($evaluation->module_id)
            ->active()
            ->exists();

        if (! $isAssigned) {
            return response()->json([
                'message' => "Vous n'êtes pas affecté à ce module.",
            ], 403);
        }

        $history = $this->auditService->getGradeHistory($grade);

        return response()->json([
            'data' => GradeHistoryResource::collection($history),
            'grade' => [
                'id' => $grade->id,
                'current_score' => $grade->score,
                'is_absent' => $grade->is_absent,
                'status' => $grade->status,
            ],
        ]);
    }

    /**
     * Request correction for a published grade
     */
    public function requestCorrection(RequestCorrectionRequest $request, int $gradeId): JsonResponse
    {
        $teacher = $request->user();
        $data = $request->validated();

        // Load grade explicitly from tenant database to avoid route binding issues
        $grade = Grade::on('tenant')->find($gradeId);

        if (! $grade) {
            return response()->json([
                'message' => 'Note introuvable.',
            ], 404);
        }

        // Load evaluation using explicit query to handle tenant connection properly
        $evaluation = ModuleEvaluationConfig::on('tenant')->find($grade->evaluation_id);

        if (! $evaluation) {
            return response()->json([
                'message' => "L'évaluation associée à cette note est introuvable (evaluation_id: {$grade->evaluation_id}).",
            ], 404);
        }

        // Verify teacher is assigned
        $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
            ->byModule($evaluation->module_id)
            ->active()
            ->exists();

        if (! $isAssigned) {
            return response()->json([
                'message' => "Vous n'êtes pas affecté à ce module.",
            ], 403);
        }

        // Check if grade requires correction request
        if (! $grade->requiresCorrectionRequest()) {
            return response()->json([
                'message' => 'Cette note peut être modifiée directement (non publiée).',
            ], 422);
        }

        try {
            $correctionRequest = $this->auditService->requestCorrection(
                $grade,
                $teacher,
                $data['proposed_value'] ?? null,
                $data['proposed_is_absent'] ?? false,
                $data['reason']
            );

            return response()->json([
                'message' => 'Demande de correction soumise.',
                'data' => [
                    'id' => $correctionRequest->id,
                    'status' => $correctionRequest->status,
                    'change' => $correctionRequest->getFormattedChange(),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Export evaluation history as Excel
     */
    public function exportEvaluationHistory(Request $request, int $evaluationId): BinaryFileResponse|JsonResponse
    {
        $teacher = $request->user();

        // Load evaluation explicitly from tenant database to avoid route binding issues
        $evaluation = ModuleEvaluationConfig::on('tenant')->find($evaluationId);

        if (! $evaluation) {
            return response()->json([
                'message' => 'Évaluation introuvable.',
            ], 404);
        }

        // Verify teacher is assigned to this module
        $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
            ->byModule($evaluation->module_id)
            ->active()
            ->exists();

        if (! $isAssigned) {
            return response()->json([
                'message' => "Vous n'êtes pas affecté à ce module.",
            ], 403);
        }

        $filename = 'historique-notes-'.str_replace(' ', '-', $evaluation->name).'-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(
            new GradeEvaluationHistoryExport($evaluation->id),
            $filename
        );
    }

    /**
     * Export module history as Excel
     */
    public function exportModuleHistory(Request $request, int $moduleId): BinaryFileResponse|JsonResponse
    {
        $teacher = $request->user();

        // Load module explicitly from tenant database to avoid route binding issues
        $module = Module::on('tenant')->find($moduleId);

        if (! $module) {
            return response()->json([
                'message' => 'Module introuvable.',
            ], 404);
        }

        // Verify teacher is assigned to this module
        $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
            ->byModule($module->id)
            ->active()
            ->exists();

        if (! $isAssigned) {
            return response()->json([
                'message' => "Vous n'êtes pas affecté à ce module.",
            ], 403);
        }

        $filename = 'historique-notes-'.str_replace(' ', '-', $module->name).'-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(
            new GradeAuditExport($module->id),
            $filename
        );
    }
}
