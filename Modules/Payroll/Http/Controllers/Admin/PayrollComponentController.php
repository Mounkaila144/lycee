<?php

namespace Modules\Payroll\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Payroll\Entities\Employee;
use Modules\Payroll\Entities\EmployeeAdvance;
use Modules\Payroll\Entities\PayrollComponent;
use Modules\Payroll\Entities\SalaryScale;
use Modules\Payroll\Services\PayrollComponentService;

/**
 * Epic 2: Éléments de Paie
 * Handles Stories 05-09
 */
class PayrollComponentController extends Controller
{
    public function __construct(
        private PayrollComponentService $componentService
    ) {}

    /**
     * Story 05: Manage salary scales
     */
    public function indexSalaryScales(Request $request): JsonResponse
    {
        $query = SalaryScale::on('tenant');

        if ($request->boolean('active_only')) {
            $query->active();
        }

        return response()->json($query->orderBy('code')->get());
    }

    public function storeSalaryScale(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|unique:tenant.salary_scales,code',
            'name' => 'required|string',
            'type' => 'required|in:teaching,administrative,technical,management,other',
            'grades' => 'required|array',
            'effective_from' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $scale = $this->componentService->createSalaryScale($request->all());

        return response()->json([
            'message' => 'Grille salariale créée avec succès',
            'data' => $scale,
        ], 201);
    }

    /**
     * Story 06-08: Manage payroll components
     */
    public function indexComponents(Request $request): JsonResponse
    {
        $query = PayrollComponent::on('tenant')->with('employee');

        if ($employeeId = $request->input('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        if ($type = $request->input('type')) {
            $query->byType($type);
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    public function storeComponent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:tenant.employees,id',
            'component_type' => 'required|in:bonus,allowance,deduction,overtime,commission,benefit,reimbursement',
            'name' => 'required|string',
            'calculation_type' => 'required|in:fixed,percentage,hourly,daily',
            'amount' => 'required_if:calculation_type,fixed|nullable|numeric',
            'percentage' => 'required_if:calculation_type,percentage|nullable|numeric',
            'rate' => 'required_if:calculation_type,hourly,daily|nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $employee = Employee::on('tenant')->findOrFail($request->employee_id);
        $type = $request->component_type;

        if ($type === 'bonus') {
            $component = $this->componentService->createBonus($employee, $request->all());
        } elseif ($type === 'allowance') {
            $component = $this->componentService->createAllowance($employee, $request->all());
        } elseif ($type === 'deduction') {
            $component = $this->componentService->createDeduction($employee, $request->all());
        } else {
            $component = $this->componentService->createComponent($employee, $type, $request->all());
        }

        return response()->json([
            'message' => 'Élément de paie créé avec succès',
            'data' => $component,
        ], 201);
    }

    /**
     * Story 09: Manage employee advances
     */
    public function indexAdvances(Request $request): JsonResponse
    {
        $query = EmployeeAdvance::on('tenant')->with('employee');

        if ($employeeId = $request->input('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->orderBy('request_date', 'desc')->get());
    }

    public function requestAdvance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:tenant.employees,id',
            'amount' => 'required|numeric|min:0',
            'advance_type' => 'required|in:salary_advance,loan,emergency,other',
            'reason' => 'required|string',
            'number_of_installments' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $employee = Employee::on('tenant')->findOrFail($request->employee_id);

        $advance = $this->componentService->requestAdvance(
            $employee,
            $request->amount,
            $request->advance_type,
            $request->reason,
            $request->only(['number_of_installments', 'first_deduction_date', 'requested_by'])
        );

        return response()->json([
            'message' => 'Demande d\'avance créée avec succès',
            'data' => $advance,
        ], 201);
    }

    public function approveAdvance(Request $request, int $id): JsonResponse
    {
        $advance = EmployeeAdvance::on('tenant')->findOrFail($id);

        $advance = $this->componentService->approveAdvance(
            $advance,
            auth()->id(),
            $request->input('notes')
        );

        return response()->json([
            'message' => 'Avance approuvée avec succès',
            'data' => $advance,
        ]);
    }

    public function disburseAdvance(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'method' => 'required|in:cash,bank_transfer,check',
            'reference' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $advance = EmployeeAdvance::on('tenant')->findOrFail($id);

        $advance = $this->componentService->disburseAdvance(
            $advance,
            $request->method,
            $request->reference
        );

        return response()->json([
            'message' => 'Avance débloquée avec succès',
            'data' => $advance,
        ]);
    }
}
