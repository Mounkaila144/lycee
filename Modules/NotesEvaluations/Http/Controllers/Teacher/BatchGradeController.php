<?php

namespace Modules\NotesEvaluations\Http\Controllers\Teacher;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Http\Requests\StoreBatchGradesRequest;
use Modules\NotesEvaluations\Services\BatchGradeService;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\StructureAcademique\Entities\TeacherModuleAssignment;

class BatchGradeController extends Controller
{
    public function __construct(
        private BatchGradeService $batchService
    ) {}

    /**
     * Validate batch grades (dry run)
     */
    public function validateBatch(Request $request, ModuleEvaluationConfig $evaluation): JsonResponse
    {
        $teacher = $request->user();

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

        $request->validate([
            'grades' => ['required', 'array', 'min:1', 'max:500'],
            'grades.*.matricule' => ['required', 'string'],
            'grades.*.score' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'grades.*.is_absent' => ['boolean'],
        ]);

        $result = $this->batchService->validateBatch($evaluation, $request->input('grades'));

        return response()->json([
            'data' => $result,
        ], $result['valid'] ? 200 : 422);
    }

    /**
     * Store batch grades
     */
    public function store(StoreBatchGradesRequest $request, ModuleEvaluationConfig $evaluation): JsonResponse
    {
        $teacher = $request->user();
        $data = $request->validated();

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

        // Validate first
        $validation = $this->batchService->validateBatch($evaluation, $data['grades']);

        if (! $validation['valid'] && ! $request->boolean('force', false)) {
            return response()->json([
                'message' => 'Validation échouée. Corrigez les erreurs ou utilisez force=true.',
                'validation' => $validation,
            ], 422);
        }

        try {
            $result = $this->batchService->processBatch(
                $evaluation,
                $data['grades'],
                $teacher,
                $data['overwrite_existing'] ?? false
            );

            $message = sprintf(
                '%d note(s) créée(s), %d mise(s) à jour, %d ignorée(s).',
                $result['created'],
                $result['updated'],
                $result['skipped']
            );

            return response()->json([
                'message' => $message,
                'data' => $result,
            ], $result['errors'] ? 207 : 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du traitement: '.$e->getMessage(),
            ], 500);
        }
    }
}
