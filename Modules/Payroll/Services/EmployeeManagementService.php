<?php

namespace Modules\Payroll\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Entities\ContractAmendment;
use Modules\Payroll\Entities\Employee;
use Modules\Payroll\Entities\EmploymentContract;

/**
 * Epic 1: Gestion Employés (Stories 01-04)
 *
 * Story 01: Create and manage employee records
 * Story 02: Create and manage employment contracts
 * Story 03: Create contract amendments
 * Story 04: Process contract terminations
 */
class EmployeeManagementService
{
    /**
     * Story 01: Create employee record
     */
    public function createEmployee(array $data): Employee
    {
        return DB::connection('tenant')->transaction(function () use ($data) {
            // Generate employee code if not provided
            if (! isset($data['employee_code'])) {
                $data['employee_code'] = $this->generateEmployeeCode();
            }

            // Set default status
            $data['status'] = $data['status'] ?? 'active';

            return Employee::on('tenant')->create($data);
        });
    }

    /**
     * Update employee record
     */
    public function updateEmployee(Employee $employee, array $data): Employee
    {
        $employee->update($data);

        return $employee->fresh();
    }

    /**
     * Story 02: Create employment contract
     */
    public function createContract(Employee $employee, array $contractData): EmploymentContract
    {
        return DB::connection('tenant')->transaction(function () use ($employee, $contractData) {
            // Generate contract number if not provided
            if (! isset($contractData['contract_number'])) {
                $contractData['contract_number'] = $this->generateContractNumber();
            }

            // Set employee ID
            $contractData['employee_id'] = $employee->id;

            // Set default status
            $contractData['status'] = $contractData['status'] ?? 'draft';

            // Validate contract dates
            if (isset($contractData['end_date']) && $contractData['end_date'] <= $contractData['start_date']) {
                throw new \Exception('La date de fin doit être postérieure à la date de début');
            }

            // Create contract
            $contract = EmploymentContract::on('tenant')->create($contractData);

            return $contract->fresh(['employee', 'salaryScale']);
        });
    }

    /**
     * Activate contract
     */
    public function activateContract(EmploymentContract $contract): EmploymentContract
    {
        return DB::connection('tenant')->transaction(function () use ($contract) {
            if ($contract->status !== 'draft') {
                throw new \Exception('Seuls les contrats en brouillon peuvent être activés');
            }

            // Deactivate other active contracts for the same employee
            EmploymentContract::on('tenant')
                ->where('employee_id', $contract->employee_id)
                ->where('status', 'active')
                ->where('id', '!=', $contract->id)
                ->update(['status' => 'expired']);

            // Activate contract
            $contract->update([
                'status' => 'active',
                'signature_date' => $contract->signature_date ?? now(),
            ]);

            // Update employee status
            $contract->employee->update(['status' => 'active']);

            return $contract->fresh();
        });
    }

    /**
     * Story 03: Create contract amendment
     */
    public function createAmendment(
        EmploymentContract $contract,
        string $amendmentType,
        array $newValues,
        array $options = []
    ): ContractAmendment {
        return DB::connection('tenant')->transaction(function () use ($contract, $amendmentType, $newValues, $options) {
            if (! $contract->canBeAmended()) {
                throw new \Exception('Ce contrat ne peut pas être modifié');
            }

            // Get previous values
            $previousValues = $this->getContractValues($contract, array_keys($newValues));

            // Generate amendment number
            $amendmentNumber = $this->generateAmendmentNumber($contract);

            // Create amendment
            $amendment = ContractAmendment::on('tenant')->create([
                'contract_id' => $contract->id,
                'amendment_number' => $amendmentNumber,
                'amendment_type' => $amendmentType,
                'effective_date' => $options['effective_date'] ?? now(),
                'previous_values' => $previousValues,
                'new_values' => $newValues,
                'description' => $options['description'] ?? null,
                'status' => $options['status'] ?? 'draft',
                'requested_by' => $options['requested_by'] ?? null,
            ]);

            return $amendment->fresh(['contract']);
        });
    }

    /**
     * Approve and apply amendment
     */
    public function approveAmendment(ContractAmendment $amendment, int $userId, ?string $notes = null): ContractAmendment
    {
        return DB::connection('tenant')->transaction(function () use ($amendment, $userId, $notes) {
            $amendment->approve($userId, $notes);

            // If effective date is today or past, apply changes
            if ($amendment->effective_date->lte(now())) {
                $this->applyAmendment($amendment);
            }

            return $amendment->fresh();
        });
    }

    /**
     * Apply amendment to contract
     */
    protected function applyAmendment(ContractAmendment $amendment): void
    {
        $contract = $amendment->contract;

        // Apply new values to contract
        $contract->update($amendment->new_values);

        // Mark amendment as active
        $amendment->activate();
    }

    /**
     * Story 04: Terminate contract
     */
    public function terminateContract(
        EmploymentContract $contract,
        Carbon $terminationDate,
        string $reason,
        array $options = []
    ): EmploymentContract {
        return DB::connection('tenant')->transaction(function () use ($contract, $terminationDate, $reason) {
            if (! $contract->isActive()) {
                throw new \Exception('Seuls les contrats actifs peuvent être résiliés');
            }

            // Update contract
            $contract->update([
                'status' => 'terminated',
                'end_date' => $terminationDate,
            ]);

            // Update employee
            $contract->employee->update([
                'status' => 'terminated',
                'termination_date' => $terminationDate,
                'termination_reason' => $reason,
            ]);

            return $contract->fresh();
        });
    }

    /**
     * Renew fixed-term contract
     */
    public function renewContract(
        EmploymentContract $contract,
        Carbon $newStartDate,
        ?Carbon $newEndDate = null,
        array $options = []
    ): EmploymentContract {
        return DB::connection('tenant')->transaction(function () use ($contract, $newStartDate, $newEndDate, $options) {
            if (! $contract->canBeRenewed()) {
                throw new \Exception('Ce contrat ne peut pas être renouvelé');
            }

            // Create new contract
            $newContractData = [
                'contract_number' => $this->generateContractNumber(),
                'contract_type' => $contract->contract_type,
                'start_date' => $newStartDate,
                'end_date' => $newEndDate,
                'probation_end_date' => null,
                'weekly_hours' => $contract->weekly_hours,
                'work_schedule' => $contract->work_schedule,
                'base_salary' => $options['base_salary'] ?? $contract->base_salary,
                'salary_scale_id' => $contract->salary_scale_id,
                'salary_scale_grade' => $contract->salary_scale_grade,
                'benefits' => $contract->benefits,
                'contract_terms' => $contract->contract_terms,
                'job_description' => $contract->job_description,
                'status' => 'draft',
                'is_renewable' => $contract->is_renewable,
                'renewed_from_contract_id' => $contract->id,
            ];

            $newContract = $this->createContract($contract->employee, $newContractData);

            // Link contracts
            $contract->update(['renewed_to_contract_id' => $newContract->id]);

            return $newContract;
        });
    }

    /**
     * Generate unique employee code
     */
    protected function generateEmployeeCode(): string
    {
        $year = now()->year;
        $lastEmployee = Employee::on('tenant')
            ->where('employee_code', 'like', "EMP-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastEmployee) {
            preg_match('/EMP-\d+-(\d+)/', $lastEmployee->employee_code, $matches);
            $sequence = isset($matches[1]) ? (int) $matches[1] + 1 : 1;
        }

        return sprintf('EMP-%d-%05d', $year, $sequence);
    }

    /**
     * Generate unique contract number
     */
    protected function generateContractNumber(): string
    {
        $year = now()->year;
        $lastContract = EmploymentContract::on('tenant')
            ->where('contract_number', 'like', "CONT-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastContract) {
            preg_match('/CONT-\d+-(\d+)/', $lastContract->contract_number, $matches);
            $sequence = isset($matches[1]) ? (int) $matches[1] + 1 : 1;
        }

        return sprintf('CONT-%d-%05d', $year, $sequence);
    }

    /**
     * Generate unique amendment number
     */
    protected function generateAmendmentNumber(EmploymentContract $contract): string
    {
        $amendmentCount = $contract->amendments()->count() + 1;

        return "{$contract->contract_number}-AVT{$amendmentCount}";
    }

    /**
     * Get current contract values for specified fields
     */
    protected function getContractValues(EmploymentContract $contract, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = $contract->{$field};
        }

        return $values;
    }
}
