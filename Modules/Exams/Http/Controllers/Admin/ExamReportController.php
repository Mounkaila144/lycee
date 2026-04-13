<?php

namespace Modules\Exams\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Exams\Entities\ExamSession;
use Modules\Exams\Services\ExamReportService;

class ExamReportController extends Controller
{
    public function __construct(private ExamReportService $reportService) {}

    public function attendanceReport(ExamSession $session): JsonResponse
    {
        $report = $this->reportService->generateAttendanceReport($session);

        return response()->json($report);
    }

    public function exportAttendance(Request $request, ExamSession $session): JsonResponse
    {
        $format = $request->input('format', 'excel');
        $export = $this->reportService->exportAttendanceReport($session, $format);

        return response()->json($export);
    }

    public function incidentReport(ExamSession $session): JsonResponse
    {
        $report = $this->reportService->generateIncidentReport($session);

        return response()->json($report);
    }

    public function exportIncidents(Request $request, ExamSession $session): JsonResponse
    {
        $format = $request->input('format', 'pdf');
        $export = $this->reportService->exportIncidentReport($session, $format);

        return response()->json($export);
    }

    public function statistics(Request $request): JsonResponse
    {
        $stats = $this->reportService->generateExamStatistics(
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('module_id')
        );

        return response()->json($stats);
    }

    public function supervisorWorkload(Request $request): JsonResponse
    {
        $workload = $this->reportService->getSupervisorWorkloadStats(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json($workload);
    }

    public function roomUtilization(Request $request): JsonResponse
    {
        $utilization = $this->reportService->getRoomUtilizationStats(
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json($utilization);
    }
}
