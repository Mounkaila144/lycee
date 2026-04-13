<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Services\PerformanceAnalyticsService;

class AnalyticsController extends Controller
{
    public function __construct(
        protected PerformanceAnalyticsService $analyticsService
    ) {}

    /**
     * Get KPIs for a semester
     * GET /api/admin/analytics/semesters/{semester}/kpis
     */
    public function kpis(int $semesterId, Request $request): JsonResponse
    {
        $programmeId = $request->query('programme_id');

        $kpis = $this->analyticsService->getKPIs(
            $semesterId,
            $programmeId ? (int) $programmeId : null
        );

        return response()->json([
            'data' => $kpis,
        ]);
    }

    /**
     * Get weak modules analysis for a semester
     * GET /api/admin/analytics/semesters/{semester}/weak-modules
     */
    public function weakModules(int $semesterId, Request $request): JsonResponse
    {
        $programmeId = $request->query('programme_id');

        $modules = $this->analyticsService->getWeakModules(
            $semesterId,
            $programmeId ? (int) $programmeId : null
        );

        return response()->json([
            'data' => $modules,
            'meta' => [
                'total' => $modules->count(),
                'filter' => [
                    'programme_id' => $programmeId,
                ],
            ],
        ]);
    }

    /**
     * Get cohort analysis for a semester
     * GET /api/admin/analytics/semesters/{semester}/cohort-analysis
     */
    public function cohortAnalysis(int $semesterId, Request $request): JsonResponse
    {
        $programmeId = $request->query('programme_id');

        $analysis = $this->analyticsService->getCohortAnalysis(
            $semesterId,
            $programmeId ? (int) $programmeId : null
        );

        return response()->json([
            'data' => $analysis,
        ]);
    }

    /**
     * Get at-risk students for a semester
     * GET /api/admin/analytics/semesters/{semester}/at-risk-students
     */
    public function atRiskStudents(int $semesterId, Request $request): JsonResponse
    {
        $threshold = $request->query('threshold', 60);

        $students = $this->analyticsService->getAtRiskStudents(
            $semesterId,
            (float) $threshold
        );

        return response()->json([
            'data' => $students,
            'meta' => [
                'total' => $students->count(),
                'threshold' => (float) $threshold,
            ],
        ]);
    }

    /**
     * Get correlation matrix between modules
     * GET /api/admin/analytics/semesters/{semester}/correlation-matrix
     */
    public function correlationMatrix(int $semesterId, Request $request): JsonResponse
    {
        $maxModules = $request->query('max_modules', 10);

        $matrix = $this->analyticsService->getCorrelationMatrix(
            $semesterId,
            (int) $maxModules
        );

        return response()->json([
            'data' => $matrix,
        ]);
    }

    /**
     * Get complete dashboard data for a semester
     * GET /api/admin/analytics/semesters/{semester}/dashboard
     */
    public function dashboard(int $semesterId, Request $request): JsonResponse
    {
        $programmeId = $request->query('programme_id');

        $dashboard = $this->analyticsService->getDashboardData(
            $semesterId,
            $programmeId ? (int) $programmeId : null
        );

        return response()->json([
            'data' => $dashboard,
        ]);
    }

    /**
     * Get historical comparison across academic years
     * GET /api/admin/analytics/academic-years/{academicYear}/historical-comparison
     */
    public function historicalComparison(int $academicYearId): JsonResponse
    {
        $comparison = $this->analyticsService->getHistoricalComparison($academicYearId);

        return response()->json([
            'data' => $comparison,
        ]);
    }
}
