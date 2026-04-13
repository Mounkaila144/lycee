<?php

namespace Modules\Payroll\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Payroll\Entities\ContractAmendment;
use Modules\Payroll\Entities\Employee;
use Modules\Payroll\Entities\EmploymentContract;
use Modules\Payroll\Services\EmployeeManagementService;

/**
 * Epic 1: Gestion Employés
 * Handles Stories 01-04
 */
class EmployeeController extends Controller
{
    public function __construct(
        private EmployeeManagementService $employeeService
    ) {}

    /**
     * List employees
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::on('tenant')->with(['contracts', 'payrollComponents']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($department = $request->input('department')) {
            $query->byDepartment($department);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('employee_code', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 15);

        return response()->json($query->paginate($perPage));
    }

    /**
     * Story 01: Create employee
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_code' => 'nullable|string|unique:tenant.employees,employee_code',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:tenant.employees,email',
            'phone' => 'nullable|string',
            'cin' => 'nullable|string',
            'cnss_number' => 'nullable|string',
            'hire_date' => 'required|date',
            'department' => 'nullable|string',
            'position' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,suspended,terminated',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $employee = $this->employeeService->createEmployee($request->all());

        return response()->json([
            'message' => 'Employé créé avec succès',
            'data' => $employee,
        ], 201);
    }

    /**
     * Show employee
     */
    public function show(int $id): JsonResponse
    {
        $employee = Employee::on('tenant')
            ->with(['contracts', 'advances', 'payrollRecords', 'payrollComponents'])
            ->findOrFail($id);

        return response()->json(['data' => $employee]);
    }

    /**
     * Update employee
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $employee = Employee::on('tenant')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'email' => 'sometimes|email|unique:tenant.employees,email,'.$id,
            'status' => 'sometimes|in:active,inactive,suspended,terminated',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $employee = $this->employeeService->updateEmployee($employee, $request->all());

        return response()->json([
            'message' => 'Employé mis à jour avec succès',
            'data' => $employee,
        ]);
    }

    /**
     * Delete employee
     */
    public function destroy(int $id): JsonResponse
    {
        $employee = Employee::on('tenant')->findOrFail($id);

        if ($employee->payrollRecords()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer un employé avec des fiches de paie',
            ], 422);
        }

        $employee->delete();

        return response()->json(['message' => 'Employé supprimé avec succès'], 204);
    }

    /**
     * Story 02: Create contract
     */
    public function createContract(Request $request, int $employeeId): JsonResponse
    {
        $employee = Employee::on('tenant')->findOrFail($employeeId);

        $validator = Validator::make($request->all(), [
            'contract_type' => 'required|in:permanent,fixed_term,temporary,internship,consultant,part_time',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'base_salary' => 'required|numeric|min:0',
            'weekly_hours' => 'nullable|integer|min:1|max:168',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $contract = $this->employeeService->createContract($employee, $request->all());

        return response()->json([
            'message' => 'Contrat créé avec succès',
            'data' => $contract,
        ], 201);
    }

    /**
     * Activate contract
     */
    public function activateContract(int $employeeId, int $contractId): JsonResponse
    {
        $contract = EmploymentContract::on('tenant')
            ->where('employee_id', $employeeId)
            ->findOrFail($contractId);

        $contract = $this->employeeService->activateContract($contract);

        return response()->json([
            'message' => 'Contrat activé avec succès',
            'data' => $contract,
        ]);
    }

    /**
     * Story 03: Create amendment
     */
    public function createAmendment(Request $request, int $employeeId, int $contractId): JsonResponse
    {
        $contract = EmploymentContract::on('tenant')
            ->where('employee_id', $employeeId)
            ->findOrFail($contractId);

        $validator = Validator::make($request->all(), [
            'amendment_type' => 'required|in:salary_change,position_change,department_change,schedule_change,benefits_change,extension,other',
            'new_values' => 'required|array',
            'effective_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $amendment = $this->employeeService->createAmendment(
            $contract,
            $request->amendment_type,
            $request->new_values,
            $request->only(['effective_date', 'description', 'requested_by'])
        );

        return response()->json([
            'message' => 'Avenant créé avec succès',
            'data' => $amendment,
        ], 201);
    }

    /**
     * Approve amendment
     */
    public function approveAmendment(Request $request, int $employeeId, int $contractId, int $amendmentId): JsonResponse
    {
        $amendment = ContractAmendment::on('tenant')
            ->where('contract_id', $contractId)
            ->findOrFail($amendmentId);

        $amendment = $this->employeeService->approveAmendment(
            $amendment,
            auth()->id(),
            $request->input('notes')
        );

        return response()->json([
            'message' => 'Avenant approuvé avec succès',
            'data' => $amendment,
        ]);
    }

    /**
     * Story 04: Terminate contract
     */
    public function terminateContract(Request $request, int $employeeId, int $contractId): JsonResponse
    {
        $contract = EmploymentContract::on('tenant')
            ->where('employee_id', $employeeId)
            ->findOrFail($contractId);

        $validator = Validator::make($request->all(), [
            'termination_date' => 'required|date',
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $contract = $this->employeeService->terminateContract(
            $contract,
            Carbon::parse($request->termination_date),
            $request->reason
        );

        return response()->json([
            'message' => 'Contrat résilié avec succès',
            'data' => $contract,
        ]);
    }
}
