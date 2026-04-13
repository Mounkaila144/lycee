<?php

namespace Modules\Payroll\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Payroll\Entities\PayrollPeriod;
use Modules\Payroll\Services\PayrollReportService;

/**
 * Epic 5: Rapports RH
 * Handles Stories 18-20
 */
class PayrollReportController extends Controller
{
    public function __construct(
        private PayrollReportService $reportService
    ) {}

    /**
     * Story 18: Generate payroll journal
     */
    public function payrollJournal(int $periodId): JsonResponse
    {
        $period = PayrollPeriod::on('tenant')->findOrFail($periodId);

        $journal = $this->reportService->generatePayrollJournal($period);

        return response()->json([
            'message' => 'Journal de paie généré avec succès',
            'data' => $journal,
        ]);
    }

    /**
     * Story 19: Generate social charges report
     */
    public function socialCharges(int $periodId): JsonResponse
    {
        $period = PayrollPeriod::on('tenant')->findOrFail($periodId);

        $report = $this->reportService->getSocialChargesReport($period);

        return response()->json([
            'message' => 'Rapport des charges sociales généré avec succès',
            'data' => $report,
        ]);
    }

    /**
     * Story 20: Generate salary statistics
     */
    public function salaryStatistics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'year' => 'nullable|integer|min:2020|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $statistics = $this->reportService->getSalaryStatistics(
            $request->input('year'),
            $request->input('month')
        );

        return response()->json([
            'message' => 'Statistiques salariales générées avec succès',
            'data' => $statistics,
        ]);
    }

    /**
     * Dashboard summary
     */
    public function dashboard(Request $request): JsonResponse
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        // Get current period
        $currentPeriod = PayrollPeriod::on('tenant')
            ->forYearMonth($year, $month)
            ->first();

        $dashboard = [
            'current_period' => $currentPeriod,
            'statistics' => null,
            'recent_periods' => PayrollPeriod::on('tenant')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->limit(6)
                ->get(),
        ];

        if ($currentPeriod) {
            $dashboard['statistics'] = $this->reportService->getSalaryStatistics($year, $month);
        }

        return response()->json(['data' => $dashboard]);
    }
}
