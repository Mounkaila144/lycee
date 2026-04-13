<?php

namespace Modules\Payroll\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Entities\Employee;
use Modules\Payroll\Entities\EmployeeAdvance;
use Modules\Payroll\Entities\PayrollComponent;
use Modules\Payroll\Entities\SalaryScale;

/**
 * Epic 2: Éléments de Paie (Stories 05-09)
 *
 * Story 05: Manage salary scales
 * Story 06: Manage bonuses and allowances
 * Story 07: Calculate overtime
 * Story 08: Manage deductions
 * Story 09: Process employee advances
 */
class PayrollComponentService
{
    /**
     * Story 05: Create salary scale
     */
    public function createSalaryScale(array $data): SalaryScale
    {
        return DB::connection('tenant')->transaction(function () use ($data) {
            // Validate grades structure
            $this->validateGrades($data['grades'] ?? []);

            $data['is_active'] = $data['is_active'] ?? true;

            return SalaryScale::on('tenant')->create($data);
        });
    }

    /**
     * Update salary scale
     */
    public function updateSalaryScale(SalaryScale $scale, array $data): SalaryScale
    {
        if (isset($data['grades'])) {
            $this->validateGrades($data['grades']);
        }

        $scale->update($data);

        return $scale->fresh();
    }

    /**
     * Story 06: Create bonus or allowance
     */
    public function createBonus(Employee $employee, array $data): PayrollComponent
    {
        return $this->createComponent($employee, 'bonus', $data);
    }

    public function createAllowance(Employee $employee, array $data): PayrollComponent
    {
        return $this->createComponent($employee, 'allowance', $data);
    }

    /**
     * Story 07: Create overtime component
     */
    public function createOvertime(
        Employee $employee,
        float $hours,
        float $rate,
        Carbon $periodDate,
        array $options = []
    ): PayrollComponent {
        $data = array_merge($options, [
            'code' => 'OVERTIME-'.$periodDate->format('Y-m'),
            'name' => 'Heures supplémentaires '.$periodDate->format('m/Y'),
            'calculation_type' => 'hourly',
            'rate' => $rate,
            'amount' => $hours * $rate,
            'frequency' => 'one_time',
            'is_recurring' => false,
            'valid_from' => $periodDate->startOfMonth(),
            'valid_to' => $periodDate->endOfMonth(),
        ]);

        return $this->createComponent($employee, 'overtime', $data);
    }

    /**
     * Story 08: Create deduction
     */
    public function createDeduction(Employee $employee, array $data): PayrollComponent
    {
        return $this->createComponent($employee, 'deduction', $data);
    }

    /**
     * Story 09: Request employee advance
     */
    public function requestAdvance(
        Employee $employee,
        float $amount,
        string $type,
        string $reason,
        array $options = []
    ): EmployeeAdvance {
        return DB::connection('tenant')->transaction(function () use ($employee, $amount, $type, $reason, $options) {
            // Validate advance amount
            $this->validateAdvanceAmount($employee, $amount);

            $data = [
                'employee_id' => $employee->id,
                'advance_number' => $this->generateAdvanceNumber(),
                'advance_type' => $type,
                'amount' => $amount,
                'reason' => $reason,
                'request_date' => $options['request_date'] ?? now(),
                'requested_by' => $options['requested_by'] ?? null,
                'status' => 'pending',
                'number_of_installments' => $options['number_of_installments'] ?? 1,
                'first_deduction_date' => $options['first_deduction_date'] ?? now()->addMonth(),
                'has_interest' => $options['has_interest'] ?? false,
                'interest_rate' => $options['interest_rate'] ?? 0,
            ];

            // Calculate installment amount
            if ($data['number_of_installments'] > 0) {
                $data['installment_amount'] = $amount / $data['number_of_installments'];
            }

            $advance = EmployeeAdvance::on('tenant')->create($data);

            return $advance->fresh(['employee']);
        });
    }

    /**
     * Approve advance
     */
    public function approveAdvance(EmployeeAdvance $advance, int $userId, ?string $notes = null): EmployeeAdvance
    {
        return DB::connection('tenant')->transaction(function () use ($advance, $userId, $notes) {
            if (! $advance->canBeApproved()) {
                throw new \Exception('Cette avance ne peut pas être approuvée');
            }

            $advance->approve($userId, $notes);

            return $advance->fresh();
        });
    }

    /**
     * Disburse advance
     */
    public function disburseAdvance(
        EmployeeAdvance $advance,
        string $method,
        ?string $reference = null
    ): EmployeeAdvance {
        return DB::connection('tenant')->transaction(function () use ($advance, $method, $reference) {
            if (! $advance->canBeDisbursed()) {
                throw new \Exception('Cette avance ne peut pas être débloquée');
            }

            $advance->disburse($method, $reference);

            return $advance->fresh();
        });
    }

    /**
     * Record advance repayment
     */
    public function recordAdvanceRepayment(EmployeeAdvance $advance, float $amount): EmployeeAdvance
    {
        return DB::connection('tenant')->transaction(function () use ($advance, $amount) {
            if ($amount <= 0) {
                throw new \Exception('Le montant du remboursement doit être positif');
            }

            if ($amount > $advance->balance) {
                throw new \Exception('Le montant du remboursement dépasse le solde restant');
            }

            $advance->recordRepayment($amount);

            return $advance->fresh();
        });
    }

    /**
     * Generic method to create payroll component
     */
    protected function createComponent(Employee $employee, string $type, array $data): PayrollComponent
    {
        return DB::connection('tenant')->transaction(function () use ($employee, $type, $data) {
            $data['employee_id'] = $employee->id;
            $data['component_type'] = $type;
            $data['status'] = $data['status'] ?? 'active';

            // Generate code if not provided
            if (! isset($data['code'])) {
                $data['code'] = $this->generateComponentCode($type);
            }

            return PayrollComponent::on('tenant')->create($data);
        });
    }

    /**
     * Validate grades structure
     *
     * @param  array<int, array{grade: string, min_salary: float, max_salary: float, annual_increment?: float}>  $grades
     */
    protected function validateGrades(array $grades): void
    {
        if (empty($grades)) {
            throw new \Exception('La grille doit contenir au moins un échelon');
        }

        foreach ($grades as $grade) {
            if (! isset($grade['grade']) || ! isset($grade['min_salary']) || ! isset($grade['max_salary'])) {
                throw new \Exception('Chaque échelon doit avoir: grade, min_salary, max_salary');
            }

            if ($grade['min_salary'] >= $grade['max_salary']) {
                throw new \Exception("Le salaire minimum doit être inférieur au salaire maximum pour l'échelon {$grade['grade']}");
            }
        }
    }

    /**
     * Validate advance amount against employee salary
     */
    protected function validateAdvanceAmount(Employee $employee, float $amount): void
    {
        $contract = $employee->getCurrentContract();

        if (! $contract) {
            throw new \Exception('Aucun contrat actif trouvé pour cet employé');
        }

        // Check maximum advance amount (e.g., 3 months salary)
        $maxAdvance = $contract->base_salary * 3;
        if ($amount > $maxAdvance) {
            throw new \Exception("Le montant maximum de l'avance est {$maxAdvance} (3 mois de salaire)");
        }

        // Check total outstanding advances
        $totalOutstanding = $employee->getTotalAdvanceBalance();
        $maxOutstanding = $contract->base_salary * 5;

        if (($totalOutstanding + $amount) > $maxOutstanding) {
            throw new \Exception("Le total des avances en cours ne peut pas dépasser {$maxOutstanding}");
        }
    }

    /**
     * Generate unique component code
     */
    protected function generateComponentCode(string $type): string
    {
        $prefix = strtoupper(substr($type, 0, 3));
        $year = now()->year;
        $month = now()->format('m');

        $lastComponent = PayrollComponent::on('tenant')
            ->where('code', 'like', "{$prefix}-{$year}{$month}-%")
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastComponent) {
            preg_match("/{$prefix}-{$year}{$month}-(\d+)/", $lastComponent->code, $matches);
            $sequence = isset($matches[1]) ? (int) $matches[1] + 1 : 1;
        }

        return sprintf('%s-%s%s-%04d', $prefix, $year, $month, $sequence);
    }

    /**
     * Generate unique advance number
     */
    protected function generateAdvanceNumber(): string
    {
        $year = now()->year;
        $lastAdvance = EmployeeAdvance::on('tenant')
            ->where('advance_number', 'like', "ADV-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastAdvance) {
            preg_match('/ADV-\d+-(\d+)/', $lastAdvance->advance_number, $matches);
            $sequence = isset($matches[1]) ? (int) $matches[1] + 1 : 1;
        }

        return sprintf('ADV-%d-%05d', $year, $sequence);
    }
}
