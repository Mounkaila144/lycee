<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Exports\StatisticsExport;
use Modules\NotesEvaluations\Services\SuccessStatisticsService;
use Modules\StructureAcademique\Entities\Semester;

class StatisticsController extends Controller
{
    public function __construct(
        protected SuccessStatisticsService $statisticsService
    ) {}

    /**
     * Get global statistics for a semester
     * GET /api/admin/statistics/semesters/{semester}/global
     */
    public function global(Request $request, int $semesterId): JsonResponse
    {
        $programmeId = $request->query('programme_id');

        $stats = $this->statisticsService->getGlobalStatistics($semesterId, $programmeId);

        $semester = Semester::find($semesterId);

        return response()->json([
            'data' => [
                'semester' => $semester ? [
                    'id' => $semester->id,
                    'name' => $semester->name,
                ] : null,
                'statistics' => $stats,
            ],
        ]);
    }

    /**
     * Get statistics by module
     * GET /api/admin/statistics/semesters/{semester}/modules
     */
    public function modules(Request $request, int $semesterId): JsonResponse
    {
        $programmeId = $request->query('programme_id');
        $sortBy = $request->query('sort', 'success_rate');
        $sortOrder = $request->query('order', 'asc');

        $stats = $this->statisticsService->getModuleStatistics($semesterId, $programmeId);

        // Apply sorting
        if ($sortOrder === 'desc') {
            $stats = $stats->sortByDesc($sortBy)->values();
        } else {
            $stats = $stats->sortBy($sortBy)->values();
        }

        return response()->json([
            'data' => $stats,
            'meta' => [
                'total' => $stats->count(),
            ],
        ]);
    }

    /**
     * Get statistics by programme/filiere
     * GET /api/admin/statistics/semesters/{semester}/programmes
     */
    public function programmes(int $semesterId): JsonResponse
    {
        $stats = $this->statisticsService->getProgrammeStatistics($semesterId);

        return response()->json([
            'data' => $stats,
            'meta' => [
                'total' => $stats->count(),
            ],
        ]);
    }

    /**
     * Get grade distribution
     * GET /api/admin/statistics/semesters/{semester}/distribution
     */
    public function distribution(Request $request, int $semesterId): JsonResponse
    {
        $programmeId = $request->query('programme_id');

        $distribution = $this->statisticsService->getDistribution($semesterId, $programmeId);

        return response()->json([
            'data' => $distribution,
        ]);
    }

    /**
     * Get semester comparison within academic year
     * GET /api/admin/statistics/academic-years/{academicYear}/comparison
     */
    public function semesterComparison(int $academicYearId): JsonResponse
    {
        $comparison = $this->statisticsService->getSemesterComparison($academicYearId);

        return response()->json([
            'data' => $comparison,
        ]);
    }

    /**
     * Get historical comparison across years
     * GET /api/admin/statistics/programmes/{programme}/historical
     */
    public function historical(Request $request, int $programmeId): JsonResponse
    {
        $semesterOrder = $request->query('semester_order', 1);

        $comparison = $this->statisticsService->getHistoricalComparison($programmeId, $semesterOrder);

        return response()->json([
            'data' => $comparison,
        ]);
    }

    /**
     * Get top performers for a semester
     * GET /api/admin/statistics/semesters/{semester}/top-performers
     */
    public function topPerformers(Request $request, int $semesterId): JsonResponse
    {
        $limit = min($request->query('limit', 10), 100);

        $performers = $this->statisticsService->getTopPerformers($semesterId, $limit);

        return response()->json([
            'data' => $performers,
        ]);
    }

    /**
     * Export statistics to Excel
     * GET /api/admin/statistics/semesters/{semester}/export
     */
    public function export(Request $request, int $semesterId)
    {
        $format = $request->query('format', 'xlsx');
        $semester = Semester::find($semesterId);

        $filename = sprintf(
            'statistiques_%s_%s.%s',
            $semester?->name ?? $semesterId,
            now()->format('Ymd'),
            $format
        );

        // Collect all statistics
        $globalStats = $this->statisticsService->getGlobalStatistics($semesterId);
        $moduleStats = $this->statisticsService->getModuleStatistics($semesterId);
        $programmeStats = $this->statisticsService->getProgrammeStatistics($semesterId);
        $distribution = $this->statisticsService->getDistribution($semesterId);

        return (new StatisticsExport(
            $globalStats,
            $moduleStats,
            $programmeStats,
            $distribution,
            $semester
        ))->download($filename);
    }

    /**
     * Get dashboard summary
     * GET /api/admin/statistics/semesters/{semester}/dashboard
     */
    public function dashboard(int $semesterId): JsonResponse
    {
        $semester = Semester::find($semesterId);

        $globalStats = $this->statisticsService->getGlobalStatistics($semesterId);
        $distribution = $this->statisticsService->getDistribution($semesterId);
        $programmeStats = $this->statisticsService->getProgrammeStatistics($semesterId);
        $topPerformers = $this->statisticsService->getTopPerformers($semesterId, 5);

        // Get weakest modules (lowest success rate)
        $moduleStats = $this->statisticsService->getModuleStatistics($semesterId);
        $weakModules = $moduleStats->take(5);
        $strongModules = $moduleStats->sortByDesc('success_rate')->take(5)->values();

        return response()->json([
            'data' => [
                'semester' => $semester ? [
                    'id' => $semester->id,
                    'name' => $semester->name,
                ] : null,
                'global_statistics' => $globalStats,
                'distribution' => $distribution,
                'programmes' => $programmeStats->take(5),
                'top_performers' => $topPerformers,
                'weak_modules' => $weakModules,
                'strong_modules' => $strongModules,
            ],
        ]);
    }
}
