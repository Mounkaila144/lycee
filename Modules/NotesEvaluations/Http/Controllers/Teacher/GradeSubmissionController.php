<?php

namespace Modules\NotesEvaluations\Http\Controllers\Teacher;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Http\Requests\SubmitGradesRequest;
use Modules\NotesEvaluations\Services\GradeValidationService;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\StructureAcademique\Entities\TeacherModuleAssignment;

class GradeSubmissionController extends Controller
{
    public function __construct(
        private GradeValidationService $validationService
    ) {}

    /**
     * Submit grades for validation
     */
    public function submit(SubmitGradesRequest $request): JsonResponse
    {
        $teacher = $request->user();
        $data = $request->validated();

        $module = Module::find($data['module_id']);
        if (! $module) {
            return response()->json([
                'message' => 'Module introuvable.',
            ], 404);
        }

        $evaluation = isset($data['evaluation_id'])
            ? ModuleEvaluationConfig::find($data['evaluation_id'])
            : null;

        // Verify teacher is assigned
        $isAssigned = TeacherModuleAssignment::byTeacher($teacher->id)
            ->byModule($module->id)
            ->active()
            ->exists();

        if (! $isAssigned) {
            return response()->json([
                'message' => "Vous n'êtes pas affecté à ce module.",
            ], 403);
        }

        // Check if can submit
        $checks = $this->validationService->performPreSubmissionChecks($module, $evaluation);

        if (! $checks['can_submit']) {
            return response()->json([
                'message' => 'Impossible de soumettre les notes.',
                'errors' => $checks['errors'],
                'warnings' => $checks['warnings'] ?? [],
            ], 422);
        }

        try {
            $validation = $this->validationService->submitForValidation($module, $evaluation, $teacher);

            return response()->json([
                'message' => 'Notes soumises pour validation.',
                'data' => [
                    'id' => $validation->id,
                    'status' => $validation->status,
                    'submitted_at' => $validation->submitted_at->toIso8601String(),
                    'warnings' => $checks['warnings'] ?? [],
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Check submission status for teacher's modules
     */
    public function status(Request $request): JsonResponse
    {
        $teacher = $request->user();

        $assignments = TeacherModuleAssignment::byTeacher($teacher->id)
            ->active()
            ->with(['module'])
            ->get();

        $statuses = $assignments->map(function ($assignment) {
            $checks = $this->validationService->performPreSubmissionChecks(
                $assignment->module,
                null
            );

            return [
                'module_id' => $assignment->module_id,
                'module_name' => $assignment->module->name,
                'can_submit' => $checks['can_submit'],
                'errors' => $checks['errors'],
                'warnings' => $checks['warnings'] ?? [],
            ];
        });

        return response()->json([
            'data' => $statuses,
        ]);
    }
}
