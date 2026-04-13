<?php

namespace Modules\NotesEvaluations\Http\Controllers\Teacher;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Http\Requests\ImportGradesRequest;
use Modules\NotesEvaluations\Jobs\ImportGradesJob;
use Modules\NotesEvaluations\Services\GradeImportService;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\StructureAcademique\Entities\TeacherModuleAssignment;

class GradeImportController extends Controller
{
    public function __construct(
        private GradeImportService $importService
    ) {}

    /**
     * Download template for grade import
     */
    public function template(Request $request)
    {
        $evaluationId = $request->query('evaluation_id');
        $includeExisting = $request->boolean('include_existing', false);

        if (! $evaluationId) {
            return response()->json([
                'message' => 'evaluation_id est requis.',
            ], 422);
        }

        $evaluation = ModuleEvaluationConfig::find($evaluationId);
        if (! $evaluation) {
            return response()->json([
                'message' => 'Évaluation introuvable.',
            ], 404);
        }

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

        return $this->importService->generateTemplate($evaluation, $includeExisting);
    }

    /**
     * Validate uploaded file structure
     */
    public function validateFile(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $result = $this->importService->validateFile($request->file('file'));

        return response()->json([
            'data' => $result,
        ], $result['valid'] ? 200 : 422);
    }

    /**
     * Preview uploaded file data
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
            'limit' => ['integer', 'min:1', 'max:100'],
        ]);

        $limit = $request->input('limit', 50);
        $preview = $this->importService->preview($request->file('file'), $limit);

        return response()->json([
            'data' => $preview,
            'count' => count($preview),
        ]);
    }

    /**
     * Execute the import
     */
    public function execute(ImportGradesRequest $request): JsonResponse
    {
        $teacher = $request->user();
        $data = $request->validated();

        $evaluation = ModuleEvaluationConfig::find($data['evaluation_id']);
        if (! $evaluation) {
            return response()->json([
                'message' => 'Évaluation introuvable.',
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

        $file = $request->file('file');
        $mode = $data['import_mode'] ?? 'add';

        // Check if we should process async (large files)
        $preview = $this->importService->preview($file, 1000);
        $rowCount = count($preview);

        if ($rowCount > 100 && $request->boolean('async', true)) {
            // Store file temporarily and dispatch job
            $tempPath = $file->store('temp/imports', 'local');

            ImportGradesJob::dispatch(
                $tempPath,
                $evaluation->id,
                $teacher->id,
                $mode
            );

            return response()->json([
                'message' => 'Import en cours de traitement. Vous serez notifié une fois terminé.',
                'async' => true,
                'estimated_rows' => $rowCount,
            ], 202);
        }

        // Sync import for small files
        $report = $this->importService->import($file, $evaluation, $teacher, $mode);

        return response()->json([
            'message' => 'Import terminé.',
            'data' => $report,
        ]);
    }

    /**
     * Check async import status
     */
    public function status(Request $request, string $jobId): JsonResponse
    {
        // This would typically check a cache or database for job status
        // For now, return a placeholder response
        return response()->json([
            'job_id' => $jobId,
            'status' => 'pending',
            'message' => 'Vérifiez vos notifications pour le résultat final.',
        ]);
    }
}
