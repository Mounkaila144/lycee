<?php

namespace Modules\Finance\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Finance\Services\FinanceReportService;
use Modules\StructureAcademique\Entities\AcademicYear;

/**
 * Finance Report Controller
 * Handles Epic 4: Rapports (Stories 17-23)
 */
class FinanceReportController extends Controller
{
    public function __construct(
        private FinanceReportService $reportService
    ) {}

    /**
     * Story 17: Get treasury dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $academicYear = null;

        if ($academicYearId = $request->input('academic_year_id')) {
            $academicYear = AcademicYear::on('tenant')->findOrFail($academicYearId);
        }

        $data = $this->reportService->getTreasuryDashboard($academicYear);

        return response()->json(['data' => $data]);
    }

    /**
     * Story 18: Get payment journal
     */
    public function paymentJournal(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'payment_method' => 'nullable|in:cash,check,bank_transfer,card,online,mobile_money',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $this->reportService->getPaymentJournal(
            Carbon::parse($request->start_date),
            Carbon::parse($request->end_date),
            $request->payment_method
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Story 19: Get aging balance report
     */
    public function agingBalance(): JsonResponse
    {
        $data = $this->reportService->getAgingBalance();

        return response()->json(['data' => $data]);
    }

    /**
     * Story 20: Get unpaid statements
     */
    public function unpaidStatements(Request $request): JsonResponse
    {
        $studentId = $request->input('student_id');

        $data = $this->reportService->getUnpaidStatements($studentId);

        return response()->json(['data' => $data]);
    }

    /**
     * Story 21: Cash flow forecast
     */
    public function cashFlowForecast(Request $request): JsonResponse
    {
        $months = $request->input('months', 6);

        if ($months < 1 || $months > 24) {
            return response()->json(['message' => 'Le nombre de mois doit être entre 1 et 24'], 422);
        }

        $data = $this->reportService->getCashFlowForecast($months);

        return response()->json(['data' => $data]);
    }

    /**
     * Story 22: Collection statistics
     */
    public function collectionStatistics(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->start_date) : null;
        $endDate = $request->input('end_date') ? Carbon::parse($request->end_date) : null;

        $academicYear = null;
        if ($academicYearId = $request->input('academic_year_id')) {
            $academicYear = AcademicYear::on('tenant')->findOrFail($academicYearId);
        }

        $data = $this->reportService->getCollectionStatistics($startDate, $endDate, $academicYear);

        return response()->json(['data' => $data]);
    }

    /**
     * Story 23: Generate accounting export
     */
    public function accountingExport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'nullable|in:sage,ciel,custom',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $format = $request->input('format', config('finance.accounting.export_format', 'custom'));

        $data = $this->reportService->generateAccountingExport(
            Carbon::parse($request->start_date),
            Carbon::parse($request->end_date),
            $format
        );

        return response()->json(['data' => $data]);
    }

    /**
     * Clear report caches
     */
    public function clearCache(): JsonResponse
    {
        $this->reportService->clearCache();

        return response()->json(['message' => 'Cache des rapports vidé avec succès']);
    }

    /**
     * Export report to Excel
     */
    public function exportExcel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|in:payment_journal,aging_balance,unpaid_statements,collection_stats',
            'start_date' => 'required_if:report_type,payment_journal,collection_stats|nullable|date',
            'end_date' => 'required_if:report_type,payment_journal,collection_stats|nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // This would typically generate an Excel file using a package like Laravel Excel
        // For now, return the data structure

        return response()->json([
            'message' => 'Export Excel prêt',
            'data' => [
                'report_type' => $request->report_type,
                'filename' => 'finance_report_'.now()->format('Y-m-d_His').'.xlsx',
            ],
        ]);
    }

    /**
     * Export report to PDF
     */
    public function exportPdf(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|in:payment_journal,aging_balance,unpaid_statements',
            'start_date' => 'required_if:report_type,payment_journal|nullable|date',
            'end_date' => 'required_if:report_type,payment_journal|nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // This would typically generate a PDF file using a package like DomPDF or TCPDF
        // For now, return the data structure

        return response()->json([
            'message' => 'Export PDF prêt',
            'data' => [
                'report_type' => $request->report_type,
                'filename' => 'finance_report_'.now()->format('Y-m-d_His').'.pdf',
            ],
        ]);
    }

    /**
     * Get report summary (overview of all available reports)
     */
    public function summary(): JsonResponse
    {
        return response()->json([
            'data' => [
                'available_reports' => [
                    [
                        'key' => 'dashboard',
                        'name' => 'Tableau de bord trésorerie',
                        'description' => 'Vue d\'ensemble des finances',
                        'story' => 17,
                    ],
                    [
                        'key' => 'payment_journal',
                        'name' => 'Journal des encaissements',
                        'description' => 'Liste détaillée des paiements',
                        'story' => 18,
                    ],
                    [
                        'key' => 'aging_balance',
                        'name' => 'Balance âgée',
                        'description' => 'Analyse des impayés par ancienneté',
                        'story' => 19,
                    ],
                    [
                        'key' => 'unpaid_statements',
                        'name' => 'États des impayés',
                        'description' => 'Liste des factures impayées par étudiant',
                        'story' => 20,
                    ],
                    [
                        'key' => 'cash_flow_forecast',
                        'name' => 'Prévisions de trésorerie',
                        'description' => 'Prévisions d\'encaissements futurs',
                        'story' => 21,
                    ],
                    [
                        'key' => 'collection_stats',
                        'name' => 'Statistiques de recouvrement',
                        'description' => 'Indicateurs de recouvrement',
                        'story' => 22,
                    ],
                    [
                        'key' => 'accounting_export',
                        'name' => 'Export comptable',
                        'description' => 'Export pour logiciel comptable',
                        'story' => 23,
                    ],
                ],
            ],
        ]);
    }
}
