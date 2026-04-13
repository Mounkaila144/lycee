<?php

namespace Modules\Payroll\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Entities\PayrollPeriod;
use Modules\Payroll\Entities\SocialDeclaration;

/**
 * Epic 4: Déclarations Sociales (Stories 14-17)
 *
 * Story 14: Generate CNSS declaration
 * Story 15: Generate tax declaration
 * Story 16: Generate monthly declarations
 * Story 17: Generate annual tax summary
 */
class SocialDeclarationService
{
    /**
     * Story 14: Generate CNSS declaration
     */
    public function generateCNSSDeclaration(PayrollPeriod $period): SocialDeclaration
    {
        return $this->generateDeclaration($period, 'cnss');
    }

    /**
     * Story 15: Generate income tax declaration
     */
    public function generateIncomeTaxDeclaration(PayrollPeriod $period): SocialDeclaration
    {
        return $this->generateDeclaration($period, 'income_tax');
    }

    /**
     * Generate AMO declaration
     */
    public function generateAMODeclaration(PayrollPeriod $period): SocialDeclaration
    {
        return $this->generateDeclaration($period, 'amo');
    }

    /**
     * Story 16: Generate monthly declaration
     */
    protected function generateDeclaration(PayrollPeriod $period, string $type): SocialDeclaration
    {
        return DB::connection('tenant')->transaction(function () use ($period, $type) {
            if (! $period->isValidated()) {
                throw new \Exception('La paie doit être validée avant de générer les déclarations');
            }

            // Check if declaration already exists
            $existing = SocialDeclaration::on('tenant')
                ->where('payroll_period_id', $period->id)
                ->where('declaration_type', $type)
                ->first();

            if ($existing) {
                return $existing;
            }

            // Calculate totals
            $records = $period->payrollRecords;
            $totals = $this->calculateDeclarationTotals($records, $type);

            // Generate declaration number
            $declarationNumber = $this->generateDeclarationNumber($type, $period);

            // Create declaration
            $declaration = SocialDeclaration::on('tenant')->create([
                'payroll_period_id' => $period->id,
                'year' => $period->year,
                'month' => $period->month,
                'period_type' => 'monthly',
                'declaration_type' => $type,
                'declaration_number' => $declarationNumber,
                'declaration_date' => now(),
                'due_date' => $this->calculateDueDate($type, $period),
                'employer_name' => config('tenant.company_name'),
                'employer_ice' => config('tenant.ice'),
                'employer_cnss' => config('tenant.cnss_number'),
                'employer_tax_id' => config('tenant.tax_id'),
                'total_employees' => $totals['total_employees'],
                'total_gross_salary' => $totals['total_gross_salary'],
                'total_taxable_salary' => $totals['total_taxable_salary'],
                'total_employee_contributions' => $totals['total_employee_contributions'],
                'total_employer_contributions' => $totals['total_employer_contributions'],
                'total_amount_due' => $totals['total_amount_due'],
                'cnss_employee_rate' => $totals['cnss_employee_rate'] ?? null,
                'cnss_employer_rate' => $totals['cnss_employer_rate'] ?? null,
                'cnss_employee_amount' => $totals['cnss_employee_amount'] ?? 0,
                'cnss_employer_amount' => $totals['cnss_employer_amount'] ?? 0,
                'amo_employee_rate' => $totals['amo_employee_rate'] ?? null,
                'amo_employer_rate' => $totals['amo_employer_rate'] ?? null,
                'amo_employee_amount' => $totals['amo_employee_amount'] ?? 0,
                'amo_employer_amount' => $totals['amo_employer_amount'] ?? 0,
                'income_tax_withheld' => $totals['income_tax_withheld'] ?? 0,
                'professional_tax_amount' => $totals['professional_tax_amount'] ?? 0,
                'training_tax_amount' => $totals['training_tax_amount'] ?? 0,
                'employee_details' => $totals['employee_details'] ?? [],
                'calculation_details' => $totals['calculation_details'] ?? [],
                'status' => 'draft',
            ]);

            return $declaration->fresh();
        });
    }

    /**
     * Story 17: Generate annual tax summary (État 9421)
     */
    public function generateAnnualTaxSummary(int $year): SocialDeclaration
    {
        return DB::connection('tenant')->transaction(function () use ($year) {
            // Get all payroll periods for the year
            $periods = PayrollPeriod::on('tenant')
                ->forYear($year)
                ->validated()
                ->get();

            if ($periods->isEmpty()) {
                throw new \Exception("Aucune paie validée pour l'année {$year}");
            }

            // Aggregate data from all periods
            $annualData = $this->aggregateAnnualData($periods);

            // Generate declaration number
            $declarationNumber = sprintf('ANNUAL-TAX-%d', $year);

            // Create declaration
            $declaration = SocialDeclaration::on('tenant')->create([
                'payroll_period_id' => null,
                'year' => $year,
                'month' => null,
                'period_type' => 'annual',
                'declaration_type' => 'annual_tax_summary',
                'declaration_number' => $declarationNumber,
                'declaration_date' => now(),
                'due_date' => Carbon::create($year + 1, 2, 28), // Due Feb 28 of next year
                'employer_name' => config('tenant.company_name'),
                'employer_ice' => config('tenant.ice'),
                'employer_tax_id' => config('tenant.tax_id'),
                'total_employees' => $annualData['total_employees'],
                'total_gross_salary' => $annualData['total_gross_salary'],
                'total_taxable_salary' => $annualData['total_taxable_salary'],
                'income_tax_withheld' => $annualData['total_income_tax'],
                'employee_details' => $annualData['employee_details'],
                'status' => 'draft',
            ]);

            return $declaration->fresh();
        });
    }

    /**
     * Validate declaration
     */
    public function validateDeclaration(SocialDeclaration $declaration, int $userId, ?string $notes = null): SocialDeclaration
    {
        return DB::connection('tenant')->transaction(function () use ($declaration, $userId, $notes) {
            if (! $declaration->canBeValidated()) {
                throw new \Exception('Cette déclaration ne peut pas être validée');
            }

            $declaration->update([
                'status' => 'validated',
                'validated_by' => $userId,
                'validated_at' => now(),
                'validation_notes' => $notes,
            ]);

            return $declaration->fresh();
        });
    }

    /**
     * Submit declaration
     */
    public function submitDeclaration(
        SocialDeclaration $declaration,
        string $reference,
        ?string $response = null
    ): SocialDeclaration {
        return DB::connection('tenant')->transaction(function () use ($declaration, $reference, $response) {
            if (! $declaration->canBeSubmitted()) {
                throw new \Exception('Cette déclaration ne peut pas être soumise');
            }

            $declaration->update([
                'status' => 'submitted',
                'submission_date' => now(),
                'submission_reference' => $reference,
                'submission_response' => $response,
            ]);

            return $declaration->fresh();
        });
    }

    /**
     * Record declaration payment
     */
    public function recordPayment(
        SocialDeclaration $declaration,
        float $amount,
        string $method,
        string $reference
    ): SocialDeclaration {
        return DB::connection('tenant')->transaction(function () use ($declaration, $amount, $method, $reference) {
            if (! $declaration->canBePaid()) {
                throw new \Exception('Cette déclaration ne peut pas être payée');
            }

            $declaration->update([
                'status' => 'paid',
                'payment_date' => now(),
                'payment_amount' => $amount,
                'payment_method' => $method,
                'payment_reference' => $reference,
            ]);

            return $declaration->fresh();
        });
    }

    /**
     * Calculate declaration totals based on type
     */
    protected function calculateDeclarationTotals($records, string $type): array
    {
        $totals = [
            'total_employees' => $records->count(),
            'total_gross_salary' => $records->sum('gross_salary'),
            'total_taxable_salary' => $records->sum('net_taxable'),
            'total_employee_contributions' => 0,
            'total_employer_contributions' => 0,
            'total_amount_due' => 0,
            'employee_details' => [],
            'calculation_details' => [],
        ];

        switch ($type) {
            case 'cnss':
                $totals['cnss_employee_rate'] = 4.48;
                $totals['cnss_employer_rate'] = 12.89;
                $totals['cnss_employee_amount'] = $records->sum('cnss_employee');
                $totals['cnss_employer_amount'] = $records->sum('cnss_employer');
                $totals['total_employee_contributions'] = $totals['cnss_employee_amount'];
                $totals['total_employer_contributions'] = $totals['cnss_employer_amount'];
                $totals['total_amount_due'] = $totals['cnss_employee_amount'] + $totals['cnss_employer_amount'];
                break;

            case 'amo':
                $totals['amo_employee_rate'] = 2.26;
                $totals['amo_employer_rate'] = 3.96;
                $totals['amo_employee_amount'] = $records->sum('amo_employee');
                $totals['amo_employer_amount'] = $records->sum('amo_employer');
                $totals['total_employee_contributions'] = $totals['amo_employee_amount'];
                $totals['total_employer_contributions'] = $totals['amo_employer_amount'];
                $totals['total_amount_due'] = $totals['amo_employee_amount'] + $totals['amo_employer_amount'];
                break;

            case 'income_tax':
                $totals['income_tax_withheld'] = $records->sum('income_tax');
                $totals['total_amount_due'] = $totals['income_tax_withheld'];
                break;

            case 'professional_tax':
                $totals['professional_tax_amount'] = $records->sum('professional_tax');
                $totals['total_amount_due'] = $totals['professional_tax_amount'];
                break;

            case 'training_tax':
                $totals['training_tax_amount'] = $records->sum('training_tax');
                $totals['total_amount_due'] = $totals['training_tax_amount'];
                break;
        }

        // Build employee details
        foreach ($records as $record) {
            $totals['employee_details'][] = [
                'employee_code' => $record->employee->employee_code,
                'full_name' => $record->employee->full_name,
                'cnss_number' => $record->employee->cnss_number,
                'base_salary' => $record->base_salary,
                'gross_salary' => $record->gross_salary,
            ];
        }

        return $totals;
    }

    /**
     * Aggregate data for annual summary
     */
    protected function aggregateAnnualData($periods): array
    {
        $employeeData = [];
        $totalGrossSalary = 0;
        $totalTaxableSalary = 0;
        $totalIncomeTax = 0;

        foreach ($periods as $period) {
            foreach ($period->payrollRecords as $record) {
                $employeeId = $record->employee_id;

                if (! isset($employeeData[$employeeId])) {
                    $employeeData[$employeeId] = [
                        'employee_code' => $record->employee->employee_code,
                        'full_name' => $record->employee->full_name,
                        'cin' => $record->employee->cin,
                        'total_gross_salary' => 0,
                        'total_taxable_salary' => 0,
                        'total_income_tax' => 0,
                    ];
                }

                $employeeData[$employeeId]['total_gross_salary'] += $record->gross_salary;
                $employeeData[$employeeId]['total_taxable_salary'] += $record->net_taxable;
                $employeeData[$employeeId]['total_income_tax'] += $record->income_tax;

                $totalGrossSalary += $record->gross_salary;
                $totalTaxableSalary += $record->net_taxable;
                $totalIncomeTax += $record->income_tax;
            }
        }

        return [
            'total_employees' => count($employeeData),
            'total_gross_salary' => $totalGrossSalary,
            'total_taxable_salary' => $totalTaxableSalary,
            'total_income_tax' => $totalIncomeTax,
            'employee_details' => array_values($employeeData),
        ];
    }

    /**
     * Calculate due date based on declaration type
     */
    protected function calculateDueDate(string $type, PayrollPeriod $period): Carbon
    {
        // Moroccan due dates (simplified)
        $dueDate = $period->end_date->copy();

        return match ($type) {
            'cnss', 'amo' => $dueDate->addDays(10), // 10 days after month end
            'income_tax' => $dueDate->addMonths(1)->endOfMonth(), // End of next month
            'professional_tax', 'training_tax' => $dueDate->addDays(15), // 15 days after month end
            default => $dueDate->addMonth(),
        };
    }

    /**
     * Generate unique declaration number
     */
    protected function generateDeclarationNumber(string $type, PayrollPeriod $period): string
    {
        $prefix = strtoupper(substr($type, 0, 4));
        $yearMonth = $period->period_code;

        $count = SocialDeclaration::on('tenant')
            ->where('declaration_type', $type)
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->count();

        return sprintf('%s-%s-%02d', $prefix, $yearMonth, $count + 1);
    }
}
