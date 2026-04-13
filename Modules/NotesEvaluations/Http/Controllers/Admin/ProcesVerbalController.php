<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\NotesEvaluations\Entities\DeliberationSession;
use Modules\NotesEvaluations\Entities\PVGenerationLog;
use Modules\NotesEvaluations\Services\DeliberationReportService;

class ProcesVerbalController extends Controller
{
    public function __construct(
        protected DeliberationReportService $reportService
    ) {}

    /**
     * Generate PV for a deliberation session
     * POST /api/admin/deliberation-sessions/{session}/generate-pv
     */
    public function generate(int $sessionId): JsonResponse
    {
        $session = DeliberationSession::on('tenant')->findOrFail($sessionId);

        // Check if session is completed
        if ($session->status !== 'completed') {
            return response()->json([
                'message' => 'Impossible de générer le PV: la session n\'est pas terminée.',
            ], 422);
        }

        $log = $this->reportService->generateAndStorePV($session);

        return response()->json([
            'message' => 'PV généré avec succès.',
            'data' => [
                'id' => $log->id,
                'file_name' => $log->file_name,
                'file_path' => $log->file_path,
                'type' => $log->type,
                'type_label' => $log->type_label,
                'generated_at' => $log->generated_at->toIso8601String(),
                'statistics' => $log->statistics,
            ],
        ], 201);
    }

    /**
     * Preview PV data without generating file
     * GET /api/admin/deliberation-sessions/{session}/pv-preview
     */
    public function preview(int $sessionId): JsonResponse
    {
        $session = DeliberationSession::on('tenant')->findOrFail($sessionId);

        $pvData = $this->reportService->generatePVData($session);

        return response()->json([
            'data' => $pvData,
        ]);
    }

    /**
     * Search PV generation logs
     * GET /api/admin/pv/search
     */
    public function search(Request $request): JsonResponse
    {
        $filters = [
            'semester_id' => $request->query('semester_id'),
            'type' => $request->query('type'),
            'academic_year_id' => $request->query('academic_year_id'),
            'from_date' => $request->query('from_date'),
            'to_date' => $request->query('to_date'),
        ];

        $logs = $this->reportService->searchPVs($filters);

        return response()->json([
            'data' => $logs->map(fn ($log) => $this->formatLogForResponse($log)),
            'meta' => [
                'total' => $logs->count(),
            ],
        ]);
    }

    /**
     * Get PV history for a semester
     * GET /api/admin/semesters/{semester}/pv-history
     */
    public function semesterHistory(int $semesterId): JsonResponse
    {
        $logs = $this->reportService->getSemesterPVHistory($semesterId);

        return response()->json([
            'data' => $logs->map(fn ($log) => $this->formatLogForResponse($log)),
        ]);
    }

    /**
     * Download PV file
     * GET /api/admin/pv/{pvLog}/download
     */
    public function download(int $pvLogId): JsonResponse
    {
        $log = PVGenerationLog::on('tenant')->findOrFail($pvLogId);

        $contents = $this->reportService->getPVContents($log);

        if (! $contents) {
            return response()->json([
                'message' => 'Fichier PV non trouvé.',
            ], 404);
        }

        // Log download
        $log->update([
            'metadata' => array_merge($log->metadata ?? [], [
                'last_downloaded_at' => now()->toIso8601String(),
                'last_downloaded_by' => auth()->id(),
            ]),
        ]);

        return response()->json([
            'data' => $contents,
            'meta' => [
                'file_name' => $log->file_name,
                'generated_at' => $log->generated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Regenerate PV for a session
     * POST /api/admin/deliberation-sessions/{session}/regenerate-pv
     */
    public function regenerate(int $sessionId): JsonResponse
    {
        $session = DeliberationSession::on('tenant')->findOrFail($sessionId);

        if ($session->status !== 'completed') {
            return response()->json([
                'message' => 'Impossible de régénérer le PV: la session n\'est pas terminée.',
            ], 422);
        }

        $log = $this->reportService->regeneratePV($session);

        return response()->json([
            'message' => 'PV régénéré avec succès.',
            'data' => $this->formatLogForResponse($log),
        ]);
    }

    /**
     * Get summary report for an academic year
     * GET /api/admin/academic-years/{academicYear}/summary-report
     */
    public function summaryReport(int $academicYearId): JsonResponse
    {
        $report = $this->reportService->generateSummaryReport($academicYearId);

        return response()->json([
            'data' => $report,
        ]);
    }

    /**
     * Get single PV log details
     * GET /api/admin/pv/{pvLog}
     */
    public function show(int $pvLogId): JsonResponse
    {
        $log = PVGenerationLog::on('tenant')
            ->with(['session', 'semester', 'generator'])
            ->findOrFail($pvLogId);

        return response()->json([
            'data' => $this->formatLogForResponse($log, true),
        ]);
    }

    /**
     * Delete PV log
     * DELETE /api/admin/pv/{pvLog}
     */
    public function destroy(int $pvLogId): JsonResponse
    {
        $log = PVGenerationLog::on('tenant')->findOrFail($pvLogId);

        // Delete file
        if (Storage::disk('tenant')->exists($log->file_path)) {
            Storage::disk('tenant')->delete($log->file_path);
        }

        $log->delete();

        return response()->json([
            'message' => 'PV supprimé avec succès.',
        ]);
    }

    /**
     * Format log for API response
     */
    protected function formatLogForResponse(PVGenerationLog $log, bool $includeContent = false): array
    {
        $data = [
            'id' => $log->id,
            'deliberation_session_id' => $log->deliberation_session_id,
            'session' => $log->session ? [
                'id' => $log->session->id,
                'type' => $log->session->type,
                'session_date' => $log->session->session_date?->toIso8601String(),
                'status' => $log->session->status,
            ] : null,
            'semester_id' => $log->semester_id,
            'semester' => $log->semester ? [
                'id' => $log->semester->id,
                'name' => $log->semester->name,
            ] : null,
            'file_name' => $log->file_name,
            'type' => $log->type,
            'type_label' => $log->type_label,
            'generated_by' => $log->generator ? [
                'id' => $log->generator->id,
                'name' => $log->generator->name,
            ] : null,
            'generated_at' => $log->generated_at->toIso8601String(),
            'statistics' => $log->statistics,
            'metadata' => $log->metadata,
        ];

        if ($includeContent) {
            $data['content'] = $this->reportService->getPVContents($log);
        }

        return $data;
    }
}
