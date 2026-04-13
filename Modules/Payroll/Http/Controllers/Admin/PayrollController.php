<?php

namespace Modules\Payroll\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Payroll\Entities\PayrollPeriod;
use Modules\Payroll\Services\PayrollProcessingService;

/**
 * Epic 3: Traitement Paie
 * Handles Stories 10-13
 */
class PayrollController extends Controller
{
    public function __construct(
        private PayrollProcessingService $payrollService
    ) {}

    /**
     * List payroll periods
     */
    public function index(Request $request): JsonResponse
    {
        $query = PayrollPeriod::on('tenant');

        if ($year = $request->input('year')) {
            $query->forYear($year);
        }

        if ($month = $request->input('month')) {
            $query->forMonth($month);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->orderBy('year', 'desc')->orderBy('month', 'desc')->get());
    }

    /**
     * Story 10: Create and calculate payroll
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'payment_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $period = $this->payrollService->createPayrollPeriod(
            $request->year,
            $request->month,
            Carbon::parse($request->payment_date)
        );

        return response()->json([
            'message' => 'Période de paie créée avec succès',
            'data' => $period,
        ], 201);
    }

    /**
     * Show payroll period
     */
    public function show(int $id): JsonResponse
    {
        $period = PayrollPeriod::on('tenant')
            ->with(['payrollRecords.employee', 'payslips'])
            ->findOrFail($id);

        return response()->json(['data' => $period]);
    }

    /**
     * Calculate payroll
     */
    public function calculate(int $id): JsonResponse
    {
        $period = PayrollPeriod::on('tenant')->findOrFail($id);

        $period = $this->payrollService->calculatePayroll($period);

        return response()->json([
            'message' => 'Paie calculée avec succès',
            'data' => $period,
        ]);
    }

    /**
     * Story 11: Validate payroll
     */
    public function validate(int $id): JsonResponse
    {
        $period = PayrollPeriod::on('tenant')->findOrFail($id);

        $period = $this->payrollService->validatePayroll($period, auth()->id());

        return response()->json([
            'message' => 'Paie validée avec succès',
            'data' => $period,
        ]);
    }

    /**
     * Story 12: Generate payslips
     */
    public function generatePayslips(int $id): JsonResponse
    {
        $period = PayrollPeriod::on('tenant')->findOrFail($id);

        $payslips = $this->payrollService->generatePayslips($period, auth()->id());

        return response()->json([
            'message' => 'Bulletins de paie générés avec succès',
            'data' => $payslips,
            'count' => $payslips->count(),
        ]);
    }

    /**
     * Story 13: Generate bank transfer file
     */
    public function generateBankTransfers(int $id): JsonResponse
    {
        $period = PayrollPeriod::on('tenant')->findOrFail($id);

        $transfers = $this->payrollService->generateBankTransferFile($period);

        return response()->json([
            'message' => 'Fichier de virements généré avec succès',
            'data' => $transfers,
            'count' => count($transfers),
        ]);
    }

    /**
     * Mark as paid
     */
    public function markAsPaid(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $period = PayrollPeriod::on('tenant')->findOrFail($id);

        $period = $this->payrollService->markAsPaid(
            $period,
            auth()->id(),
            Carbon::parse($request->payment_date)
        );

        return response()->json([
            'message' => 'Paie marquée comme payée avec succès',
            'data' => $period,
        ]);
    }
}
