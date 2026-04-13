<?php

namespace Modules\Attendance\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Attendance\Services\AttendanceReportService;

class AttendanceReportController extends Controller
{
    public function __construct(
        private AttendanceReportService $reportService
    ) {}

    /**
     * Taux d'assiduité (Story 11)
     */
    public function getAttendanceRates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
            'group_id' => 'nullable|integer|exists:tenant.groups,id',
        ]);

        $rates = $this->reportService->getAttendanceRates(
            $validated['semester_id'],
            $validated['group_id'] ?? null
        );

        return response()->json($rates);
    }

    /**
     * Liste absentéistes (Story 12)
     */
    public function getAbsenteesList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
            'min_absence_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $absentees = $this->reportService->getAbsenteesList(
            $validated['semester_id'],
            $validated['min_absence_rate'] ?? 20.0
        );

        return response()->json($absentees);
    }

    /**
     * Statistiques détaillées (Story 13)
     */
    public function getDetailedStatistics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $statistics = $this->reportService->getDetailedStatistics($validated['semester_id']);

        return response()->json($statistics);
    }

    /**
     * Export données
     */
    public function exportData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
            'type' => 'required|in:students,modules',
        ]);

        $data = $this->reportService->exportData(
            $validated['semester_id'],
            $validated['type']
        );

        return response()->json([
            'data' => $data,
            'export_type' => $validated['type'],
            'semester_id' => $validated['semester_id'],
        ]);
    }
}
