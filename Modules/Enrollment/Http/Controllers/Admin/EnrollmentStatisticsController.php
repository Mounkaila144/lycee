<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Http\Requests\ExportReportRequest;
use Modules\Enrollment\Http\Requests\StatisticsFilterRequest;
use Modules\Enrollment\Http\Requests\YearComparisonRequest;
use Modules\Enrollment\Http\Resources\EnrollmentKPIsResource;
use Modules\Enrollment\Http\Resources\EnrollmentProgramStatsResource;
use Modules\Enrollment\Services\EnrollmentReportService;
use Modules\Enrollment\Services\EnrollmentStatisticsService;
use Modules\StructureAcademique\Entities\AcademicYear;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EnrollmentStatisticsController extends Controller
{
    public function __construct(
        private EnrollmentStatisticsService $statisticsService,
        private EnrollmentReportService $reportService
    ) {}

    /**
     * Get global KPIs for enrollments
     */
    public function kpis(StatisticsFilterRequest $request): JsonResponse
    {
        $year = $this->getAcademicYear($request);

        if (! $year) {
            return response()->json([
                'message' => 'Aucune année académique active trouvée.',
            ], 404);
        }

        $kpis = $this->statisticsService->getGlobalKPIs($year);

        return response()->json([
            'data' => new EnrollmentKPIsResource($kpis),
        ]);
    }

    /**
     * Get enrollments statistics by program
     */
    public function byProgram(StatisticsFilterRequest $request): JsonResponse
    {
        $year = $this->getAcademicYear($request);

        if (! $year) {
            return response()->json([
                'message' => 'Aucune année académique active trouvée.',
            ], 404);
        }

        $stats = $this->statisticsService->getEnrollmentsByProgram($year);

        return response()->json([
            'data' => EnrollmentProgramStatsResource::collection($stats),
        ]);
    }

    /**
     * Get enrollment trends over multiple years
     */
    public function trends(Request $request): JsonResponse
    {
        $yearsCount = min((int) $request->input('years', 5), 10);
        $programId = $request->input('program_id');

        $trends = $this->statisticsService->getEnrollmentTrends($yearsCount, $programId);

        return response()->json([
            'data' => $trends,
        ]);
    }

    /**
     * Get demographic analysis
     */
    public function demographics(StatisticsFilterRequest $request): JsonResponse
    {
        $year = $this->getAcademicYear($request);

        if (! $year) {
            return response()->json([
                'message' => 'Aucune année académique active trouvée.',
            ], 404);
        }

        $demographics = $this->statisticsService->getDemographicAnalysis($year);

        return response()->json([
            'data' => $demographics,
        ]);
    }

    /**
     * Get pedagogical enrollment analysis
     */
    public function pedagogical(StatisticsFilterRequest $request): JsonResponse
    {
        $year = $this->getAcademicYear($request);

        if (! $year) {
            return response()->json([
                'message' => 'Aucune année académique active trouvée.',
            ], 404);
        }

        $analysis = $this->statisticsService->getPedagogicalAnalysis($year);

        return response()->json([
            'data' => $analysis,
        ]);
    }

    /**
     * Get monthly trends for current year
     */
    public function monthlyTrends(StatisticsFilterRequest $request): JsonResponse
    {
        $year = $this->getAcademicYear($request);

        if (! $year) {
            return response()->json([
                'message' => 'Aucune année académique active trouvée.',
            ], 404);
        }

        $trends = $this->statisticsService->getMonthlyTrends($year);

        return response()->json([
            'data' => $trends,
        ]);
    }

    /**
     * Get student status statistics
     */
    public function statusStatistics(): JsonResponse
    {
        $stats = $this->statisticsService->getStatusStatistics();

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Compare two academic years
     */
    public function comparison(YearComparisonRequest $request): JsonResponse
    {
        $year1 = AcademicYear::on('tenant')->findOrFail($request->input('year_1_id'));
        $year2 = AcademicYear::on('tenant')->findOrFail($request->input('year_2_id'));

        $comparison = $this->statisticsService->getYearComparison($year1, $year2);

        return response()->json([
            'data' => $comparison,
        ]);
    }

    /**
     * Get alerts for enrollment issues
     */
    public function alerts(StatisticsFilterRequest $request): JsonResponse
    {
        $year = $this->getAcademicYear($request);

        if (! $year) {
            return response()->json([
                'message' => 'Aucune année académique active trouvée.',
            ], 404);
        }

        $alerts = $this->statisticsService->getAlerts($year);

        return response()->json([
            'data' => $alerts,
        ]);
    }

    /**
     * Generate executive summary PDF
     */
    public function generateExecutiveSummary(ExportReportRequest $request): JsonResponse
    {
        $year = $this->getAcademicYear($request);

        if (! $year) {
            return response()->json([
                'message' => 'Aucune année académique active trouvée.',
            ], 404);
        }

        $path = $this->reportService->generateExecutiveSummary($year);

        return response()->json([
            'message' => 'Rapport exécutif généré avec succès.',
            'data' => [
                'path' => $path,
                'download_url' => Storage::disk('tenant')->url($path),
            ],
        ]);
    }

    /**
     * Generate dashboard PDF
     */
    public function generateDashboard(ExportReportRequest $request): JsonResponse
    {
        $year = $this->getAcademicYear($request);

        if (! $year) {
            return response()->json([
                'message' => 'Aucune année académique active trouvée.',
            ], 404);
        }

        $path = $this->reportService->generateDashboardReport($year);

        return response()->json([
            'message' => 'Tableau de bord généré avec succès.',
            'data' => [
                'path' => $path,
                'download_url' => Storage::disk('tenant')->url($path),
            ],
        ]);
    }

    /**
     * Export enrollments to Excel
     */
    public function exportExcel(ExportReportRequest $request): BinaryFileResponse
    {
        $year = $this->getAcademicYear($request);

        if (! $year) {
            abort(404, 'Aucune année académique active trouvée.');
        }

        $filters = $request->only(['program_id', 'level', 'status']);

        return $this->reportService->exportToExcel($year, $filters);
    }

    /**
     * Download a generated report
     */
    public function downloadReport(Request $request): BinaryFileResponse
    {
        $path = $request->input('path');

        if (! $path || ! Storage::disk('tenant')->exists($path)) {
            abort(404, 'Rapport non trouvé.');
        }

        return response()->download(
            Storage::disk('tenant')->path($path),
            basename($path)
        );
    }

    /**
     * Clear statistics cache
     */
    public function clearCache(StatisticsFilterRequest $request): JsonResponse
    {
        $year = $this->getAcademicYear($request);

        if (! $year) {
            return response()->json([
                'message' => 'Aucune année académique active trouvée.',
            ], 404);
        }

        $this->statisticsService->clearCache($year);

        return response()->json([
            'message' => 'Cache des statistiques vidé avec succès.',
        ]);
    }

    /**
     * Get academic year from request or use active year
     */
    private function getAcademicYear(Request $request): ?AcademicYear
    {
        $yearId = $request->input('academic_year_id');

        if ($yearId) {
            return AcademicYear::on('tenant')->find($yearId);
        }

        return AcademicYear::on('tenant')->where('is_active', true)->first();
    }
}
