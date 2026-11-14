<?php

namespace Modules\Payroll\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Entities\Employee;
use Modules\Payroll\Entities\PayrollPeriod;
use Modules\Payroll\Entities\PayrollRecord;
use Modules\Payroll\Entities\Payslip;
use Modules\Payroll\Exceptions\PayrollException;

/**
 * Epic 3: Traitement Paie (Stories 10-13)
 *
 * Story 10: Calculate monthly payroll
 * Story 11: Validate payroll
 * Story 12: Generate payslips
 * Story 13: Generate bank transfer file
 */
class PayrollProcessingService
{
    public function __construct(
        protected PayrollSettingsService $settingsService,
        protected PayrollCalculator $calculator
    ) {}

    /**
     * Story 10: Create and calculate payroll period
     */
    public function createPayrollPeriod(int $year, int $month, Carbon $paymentDate): PayrollPeriod
    {
        return DB::connection('tenant')->transaction(function () use ($year, $month, $paymentDate) {
            // Check if period already exists
            $existing = PayrollPeriod::on('tenant')
                ->forYearMonth($year, $month)
                ->first();

            if ($existing) {
                throw new PayrollException("La paie pour {$month}/{$year} existe déjà");
            }

            // Create period
            $startDate = Carbon::create($year, $month, 1);
            $endDate = $startDate->copy()->endOfMonth();

            $period = PayrollPeriod::on('tenant')->create([
                'period_code' => sprintf('%04d-%02d', $year, $month),
                'name' => $startDate->translatedFormat('F Y'),
                'period_type' => 'monthly',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'year' => $year,
                'month' => $month,
                'payment_date' => $paymentDate,
                'cutoff_date' => $endDate->copy()->subDays(3),
                'status' => 'draft',
            ]);

            return $period;
        });
    }

    /**
     * Calculate payroll for a period
     */
    public function calculatePayroll(PayrollPeriod $period, ?Collection $employees = null): PayrollPeriod
    {
        return DB::connection('tenant')->transaction(function () use ($period, $employees) {
            if (! $period->canBeCalculated()) {
                throw new PayrollException('Cette paie ne peut pas être calculée');
            }

            // Get employees to process
            if (! $employees) {
                $employees = Employee::on('tenant')->active()->get();
            }

            $period->update(['status' => 'in_progress']);

            // Paramètres de paie en vigueur au début de la période (version datée ou valeurs par défaut).
            $resolvedSettings = $this->settingsService->resolveForDate($period->start_date);

            $processedCount = 0;

            foreach ($employees as $employee) {
                if ($this->calculateEmployeePayroll($period, $employee, $resolvedSettings) !== null) {
                    $processedCount++;
                }
            }

            if ($processedCount === 0) {
                throw new PayrollException('Aucun employé actif avec un contrat actif : impossible de calculer la paie');
            }

            // Update period totals
            $period->updateTotals();
            $period->update([
                'status' => 'calculated',
                'calculated_by' => auth('tenant')->id(),
                'calculated_at' => now(),
            ]);

            return $period->fresh(['payrollRecords']);
        });
    }

    /**
     * Calculate payroll for a single employee (null when the employee has no active contract).
     * An existing record is returned untouched (never recalculated).
     *
     * @param  array{settings: array<string, mixed>, version: array<string, mixed>}  $resolvedSettings
     */
    protected function calculateEmployeePayroll(PayrollPeriod $period, Employee $employee, array $resolvedSettings): ?PayrollRecord
    {
        $contract = $employee->getCurrentContract();
        if (! $contract) {
            return null;
        }

        // Check if record already exists
        $existingRecord = PayrollRecord::on('tenant')
            ->where('payroll_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->first();

        if ($existingRecord) {
            return $existingRecord;
        }

        // Get payroll components
        $components = $employee->payrollComponents()
            ->active()
            ->valid($period->end_date)
            ->get();

        // Calculate base salary and working days
        $baseSalary = $contract->base_salary;
        $daysInMonth = $period->end_date->daysInMonth;
        $daysWorked = $daysInMonth; // TODO: Get from attendance
        $daysAbsent = 0; // TODO: Get from attendance

        // Calculate earnings
        $earnings = $this->calculateEarnings($components, $baseSalary);
        $overtimePay = $this->calculateOvertime($components, $baseSalary);

        $totalEarnings = array_sum($earnings) + $overtimePay;
        $grossSalary = $baseSalary + $totalEarnings;

        // Cotisations et impôt selon les paramètres de paie configurés
        $calculation = $this->calculator->calculate($grossSalary, $resolvedSettings['settings']);

        // Calculate advance deductions
        $advanceDeductions = $this->calculateAdvanceDeductions($employee, $period);

        // Calculate other deductions
        $otherDeductions = $this->calculateOtherDeductions($components);

        $totalDeductions = $calculation['total_employee_contributions'] + $calculation['income_tax']
            + $advanceDeductions + array_sum($otherDeductions);

        $netSalary = $grossSalary - $totalDeductions;
        $totalEmployerCharges = $calculation['total_employer_charges'];
        $totalCost = $grossSalary + $totalEmployerCharges;

        $settingsMeta = [
            'format' => 'configurable',
            'settings_version_id' => $resolvedSettings['version']['id'] ?? null,
            'settings_version' => $resolvedSettings['version']['version'] ?? null,
            'settings_effective_date' => $resolvedSettings['version']['effective_date'] ?? null,
            'settings_is_default' => $resolvedSettings['version']['is_default'] ?? false,
            'currency_label' => $calculation['currency_label'],
        ];

        // Create payroll record
        return PayrollRecord::on('tenant')->create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'contract_id' => $contract->id,
            'base_salary' => $baseSalary,
            'days_worked' => $daysWorked,
            'days_absent' => $daysAbsent,
            'hours_worked' => $daysWorked * 8, // Assuming 8 hours/day
            'overtime_hours' => $components->where('component_type', 'overtime')->sum('rate') ?? 0,
            'bonuses' => $earnings['bonuses'] ?? 0,
            'allowances' => $earnings['allowances'] ?? 0,
            'overtime_pay' => $overtimePay,
            'commissions' => $earnings['commissions'] ?? 0,
            'other_earnings' => $earnings['other'] ?? 0,
            'total_earnings' => $totalEarnings,
            'gross_salary' => $grossSalary,
            // Colonnes historiques : total des cotisations salariales / patronales configurées
            'cnss_employee' => $calculation['total_employee_contributions'],
            'cimr_employee' => 0,
            'amo_employee' => 0,
            'income_tax' => $calculation['income_tax'],
            'advance_deductions' => $advanceDeductions,
            'loan_deductions' => 0, // TODO: Implement loan tracking
            'other_deductions' => array_sum($otherDeductions),
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
            'net_taxable' => $calculation['taxable_income'],
            'cnss_employer' => $calculation['total_employer_contributions'],
            'cimr_employer' => 0,
            'amo_employer' => 0,
            'professional_tax' => 0,
            'training_tax' => $calculation['total_additional_employer_charges'],
            'total_employer_charges' => $totalEmployerCharges,
            'total_cost' => $totalCost,
            'earnings_breakdown' => $earnings,
            'deductions_breakdown' => array_merge($settingsMeta, [
                'contributions' => array_map(fn (array $line): array => [
                    'code' => $line['code'],
                    'label' => $line['label'],
                    'base' => $line['base'],
                    'rate' => $line['employee_rate'],
                    'amount' => $line['employee_amount'],
                    'tax_deductible' => $line['tax_deductible'],
                ], $calculation['contributions']),
                'income_tax' => [
                    'label' => $calculation['income_tax_label'],
                    'taxable_before_allowance' => $calculation['taxable_before_allowance'],
                    'flat_allowance_amount' => $calculation['flat_allowance_amount'],
                    'taxable_income' => $calculation['taxable_income'],
                    'brackets' => $calculation['income_tax_brackets'],
                    'amount' => $calculation['income_tax'],
                ],
                'advances' => $advanceDeductions,
                'other' => $otherDeductions,
            ]),
            'charges_breakdown' => array_merge($settingsMeta, [
                'contributions' => array_map(fn (array $line): array => [
                    'code' => $line['code'],
                    'label' => $line['label'],
                    'base' => $line['base'],
                    'rate' => $line['employer_rate'],
                    'amount' => $line['employer_amount'],
                ], $calculation['contributions']),
                'employer_charges' => $calculation['employer_charges'],
            ]),
            'status' => 'calculated',
            'payment_status' => 'pending',
        ]);
    }

    /**
     * Calculate employee earnings from components
     */
    protected function calculateEarnings(Collection $components, float $baseSalary): array
    {
        $earnings = [
            'bonuses' => 0,
            'allowances' => 0,
            'commissions' => 0,
            'other' => 0,
        ];

        foreach ($components as $component) {
            if (! in_array($component->component_type, ['bonus', 'allowance', 'commission', 'benefit'])) {
                continue;
            }

            $amount = $component->calculateAmount($baseSalary);

            switch ($component->component_type) {
                case 'bonus':
                    $earnings['bonuses'] += $amount;
                    break;
                case 'allowance':
                case 'benefit':
                    $earnings['allowances'] += $amount;
                    break;
                case 'commission':
                    $earnings['commissions'] += $amount;
                    break;
                default:
                    $earnings['other'] += $amount;
            }
        }

        return $earnings;
    }

    /**
     * Calculate overtime pay
     */
    protected function calculateOvertime(Collection $components, float $baseSalary): float
    {
        $overtimeComponents = $components->where('component_type', 'overtime');

        return $overtimeComponents->sum(function ($component) use ($baseSalary) {
            return $component->calculateAmount($baseSalary);
        });
    }

    /**
     * Calculate advance deductions
     */
    protected function calculateAdvanceDeductions(Employee $employee, PayrollPeriod $period): float
    {
        $advances = $employee->advances()->repaying()->get();
        $totalDeduction = 0;

        foreach ($advances as $advance) {
            if ($advance->installment_amount > 0) {
                $totalDeduction += $advance->installment_amount;
                // Record repayment would be done after payroll is paid
            }
        }

        return $totalDeduction;
    }

    /**
     * Calculate other deductions from components
     */
    protected function calculateOtherDeductions(Collection $components): array
    {
        $deductions = [];

        foreach ($components->where('component_type', 'deduction') as $component) {
            $deductions[$component->code] = $component->amount ?? 0;
        }

        return $deductions;
    }

    /**
     * Story 11: Validate payroll
     */
    public function validatePayroll(PayrollPeriod $period, int $userId): PayrollPeriod
    {
        return DB::connection('tenant')->transaction(function () use ($period, $userId) {
            if (! $period->canBeValidated()) {
                throw new PayrollException('Cette paie ne peut pas être validée');
            }

            $period->update([
                'status' => 'validated',
                'validated_by' => $userId,
                'validated_at' => now(),
            ]);

            // Update all records to validated
            $period->payrollRecords()->update(['status' => 'validated']);

            return $period->fresh();
        });
    }

    /**
     * Story 12: Generate payslips
     */
    public function generatePayslips(PayrollPeriod $period, int $userId): Collection
    {
        return DB::connection('tenant')->transaction(function () use ($period, $userId) {
            if (! $period->isValidated()) {
                throw new PayrollException('La paie doit être validée avant de générer les bulletins');
            }

            $payslips = collect();

            foreach ($period->payrollRecords as $record) {
                $payslip = $this->generatePayslip($record, $userId);
                $payslips->push($payslip);
            }

            return $payslips;
        });
    }

    /**
     * Generate single payslip
     */
    protected function generatePayslip(PayrollRecord $record, int $userId): Payslip
    {
        // Check if payslip already exists
        $existing = Payslip::on('tenant')
            ->where('payroll_record_id', $record->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Generate payslip number
        $payslipNumber = $this->generatePayslipNumber($record->payrollPeriod);

        // Create payslip
        $payslip = Payslip::on('tenant')->create([
            'payroll_record_id' => $record->id,
            'employee_id' => $record->employee_id,
            'payroll_period_id' => $record->payroll_period_id,
            'payslip_number' => $payslipNumber,
            'issue_date' => now(),
            'status' => 'draft',
            'generated_by' => $userId,
            'generated_at' => now(),
        ]);

        // TODO: Generate PDF
        // $this->generatePayslipPdf($payslip);

        $payslip->update(['status' => 'generated']);

        return $payslip;
    }

    /**
     * Story 13: Generate bank transfer file
     */
    public function generateBankTransferFile(PayrollPeriod $period): array
    {
        if (! $period->isValidated()) {
            throw new PayrollException('La paie doit être validée avant de générer le fichier de virement');
        }

        $transfers = [];

        foreach ($period->payrollRecords as $record) {
            $employee = $record->employee;

            if (! $employee->bank_account_number) {
                continue; // Skip employees without bank account
            }

            $transfers[] = [
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->full_name,
                'bank_account' => $employee->bank_account_number,
                'rib' => $employee->rib,
                'amount' => $record->net_salary,
                'reference' => $record->payrollPeriod->period_code,
            ];
        }

        return $transfers;
    }

    /**
     * Mark payroll as paid
     */
    public function markAsPaid(PayrollPeriod $period, int $userId, Carbon $paymentDate): PayrollPeriod
    {
        return DB::connection('tenant')->transaction(function () use ($period, $paymentDate) {
            if (! $period->canBePaid()) {
                throw new PayrollException('Cette paie ne peut pas être marquée comme payée');
            }

            $period->update([
                'status' => 'paid',
                'payment_date' => $paymentDate,
            ]);

            // Update all records
            $period->payrollRecords()->update([
                'status' => 'paid',
                'payment_status' => 'paid',
                'payment_date' => $paymentDate,
            ]);

            // Process advance repayments
            foreach ($period->payrollRecords as $record) {
                $this->processAdvanceRepayments($record);
            }

            return $period->fresh();
        });
    }

    /**
     * Process advance repayments after payment
     */
    protected function processAdvanceRepayments(PayrollRecord $record): void
    {
        $employee = $record->employee;
        $advances = $employee->advances()->repaying()->get();

        foreach ($advances as $advance) {
            if ($advance->installment_amount > 0 && $record->advance_deductions > 0) {
                $advance->recordRepayment($advance->installment_amount);
            }
        }
    }

    /**
     * Generate unique payslip number
     */
    protected function generatePayslipNumber(PayrollPeriod $period): string
    {
        $count = Payslip::on('tenant')
            ->where('payroll_period_id', $period->id)
            ->count();

        return sprintf('%s-%04d', $period->period_code, $count + 1);
    }
}
