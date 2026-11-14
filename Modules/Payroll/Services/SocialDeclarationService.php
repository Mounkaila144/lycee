<?php

namespace Modules\Payroll\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Entities\PayrollPeriod;
use Modules\Payroll\Entities\SocialDeclaration;
use Modules\Payroll\Exceptions\PayrollException;

/**
 * Epic 4: Déclarations Sociales (Stories 14-17)
 *
 * Les montants sont agrégés à partir des bulletins calculés, par code de
 * cotisation / charge configuré dans les paramètres de paie (aucun organisme fixe).
 *
 * Types stockés (colonne enum historique) :
 * - 'cnss'         : cotisations sociales (toutes les cotisations configurées, détail par code)
 * - 'income_tax'   : impôt sur salaires retenu à la source (barème configuré)
 * - 'training_tax' : charges patronales additionnelles (taxes sur la masse salariale)
 */
class SocialDeclarationService
{
    public const TYPE_SOCIAL_CONTRIBUTIONS = 'cnss';

    public const TYPE_INCOME_TAX = 'income_tax';

    public const TYPE_EMPLOYER_CHARGES = 'training_tax';

    /**
     * Story 14: Déclaration des cotisations sociales (agrégées par code configuré)
     */
    public function generateSocialContributionsDeclaration(PayrollPeriod $period): SocialDeclaration
    {
        return $this->generateDeclaration($period, self::TYPE_SOCIAL_CONTRIBUTIONS);
    }

    /**
     * Story 15: Déclaration de l'impôt sur salaires retenu
     */
    public function generateIncomeTaxDeclaration(PayrollPeriod $period): SocialDeclaration
    {
        return $this->generateDeclaration($period, self::TYPE_INCOME_TAX);
    }

    /**
     * Déclaration des charges patronales additionnelles (agrégées par code configuré)
     */
    public function generateEmployerChargesDeclaration(PayrollPeriod $period): SocialDeclaration
    {
        return $this->generateDeclaration($period, self::TYPE_EMPLOYER_CHARGES);
    }

    /**
     * Story 16: Generate monthly declaration
     */
    protected function generateDeclaration(PayrollPeriod $period, string $type): SocialDeclaration
    {
        return DB::connection('tenant')->transaction(function () use ($period, $type) {
            if (! $period->isValidated()) {
                throw new PayrollException('La paie doit être validée avant de générer les déclarations');
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
                'cnss_employee_amount' => $totals['cnss_employee_amount'] ?? 0,
                'cnss_employer_amount' => $totals['cnss_employer_amount'] ?? 0,
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
     * Story 17: Generate annual tax summary (récapitulatif annuel des salaires et de l'impôt retenu)
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
                throw new PayrollException("Aucune paie validée pour l'année {$year}");
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
                throw new PayrollException('Cette déclaration ne peut pas être validée');
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
                throw new PayrollException('Cette déclaration ne peut pas être soumise');
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
                throw new PayrollException('Cette déclaration ne peut pas être payée');
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
     * Calculate declaration totals based on type (agrégation par code configuré).
     *
     * @return array<string, mixed>
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
            case self::TYPE_SOCIAL_CONTRIBUTIONS:
                $lines = $this->aggregateContributionLines($records);
                $employeeTotal = array_sum(array_column($lines, 'employee_amount'));
                $employerTotal = array_sum(array_column($lines, 'employer_amount'));

                $totals['cnss_employee_amount'] = $employeeTotal;
                $totals['cnss_employer_amount'] = $employerTotal;
                $totals['total_employee_contributions'] = $employeeTotal;
                $totals['total_employer_contributions'] = $employerTotal;
                $totals['total_amount_due'] = $employeeTotal + $employerTotal;
                $totals['calculation_details'] = [
                    'label' => 'Cotisations sociales',
                    'lines' => $lines,
                    'settings_versions' => $this->settingsVersionsUsed($records),
                ];
                break;

            case self::TYPE_INCOME_TAX:
                $totals['income_tax_withheld'] = $records->sum('income_tax');
                $totals['total_amount_due'] = $totals['income_tax_withheld'];
                $totals['calculation_details'] = [
                    'label' => $this->incomeTaxLabel($records),
                    'total_taxable_income' => $records->sum('net_taxable'),
                    'settings_versions' => $this->settingsVersionsUsed($records),
                ];
                break;

            case self::TYPE_EMPLOYER_CHARGES:
                $lines = $this->aggregateEmployerChargeLines($records);
                $total = array_sum(array_column($lines, 'amount'));

                $totals['training_tax_amount'] = $total;
                $totals['total_employer_contributions'] = $total;
                $totals['total_amount_due'] = $total;
                $totals['calculation_details'] = [
                    'label' => 'Charges patronales additionnelles',
                    'lines' => $lines,
                    'settings_versions' => $this->settingsVersionsUsed($records),
                ];
                break;
        }

        // Build employee details
        foreach ($records as $record) {
            $detail = [
                'employee_code' => $record->employee->employee_code,
                'full_name' => $record->employee->full_name,
                'cnss_number' => $record->employee->cnss_number,
                'base_salary' => $record->base_salary,
                'gross_salary' => $record->gross_salary,
            ];

            if ($type === self::TYPE_SOCIAL_CONTRIBUTIONS) {
                $detail['contributions'] = array_map(fn (array $line): array => [
                    'code' => $line['code'],
                    'base' => $line['base'],
                    'employee_amount' => $line['employee_amount'],
                    'employer_amount' => $line['employer_amount'],
                ], $record->contributionLines());
            } elseif ($type === self::TYPE_INCOME_TAX) {
                $detail['taxable_income'] = $record->net_taxable;
                $detail['income_tax'] = $record->income_tax;
            } elseif ($type === self::TYPE_EMPLOYER_CHARGES) {
                $detail['employer_charges'] = $record->additionalEmployerChargeLines();
            }

            $totals['employee_details'][] = $detail;
        }

        return $totals;
    }

    /**
     * Somme des cotisations par code sur l'ensemble des bulletins.
     *
     * @return array<int, array{code: string, label: string, employee_rate: float|null, employer_rate: float|null, base_total: float, employee_amount: float, employer_amount: float, total: float}>
     */
    public function aggregateContributionLines($records): array
    {
        $byCode = [];

        foreach ($records as $record) {
            foreach ($record->contributionLines() as $line) {
                $code = $line['code'];

                if (! isset($byCode[$code])) {
                    $byCode[$code] = [
                        'code' => $code,
                        'label' => $line['label'],
                        'employee_rate' => $line['employee_rate'],
                        'employer_rate' => $line['employer_rate'],
                        'base_total' => 0.0,
                        'employee_amount' => 0.0,
                        'employer_amount' => 0.0,
                        'total' => 0.0,
                    ];
                }

                $byCode[$code]['base_total'] += $line['base'];
                $byCode[$code]['employee_amount'] += $line['employee_amount'];
                $byCode[$code]['employer_amount'] += $line['employer_amount'];
                $byCode[$code]['total'] += $line['employee_amount'] + $line['employer_amount'];
            }
        }

        return array_values(array_map(fn (array $line): array => array_merge($line, [
            'base_total' => round($line['base_total'], 2),
            'employee_amount' => round($line['employee_amount'], 2),
            'employer_amount' => round($line['employer_amount'], 2),
            'total' => round($line['total'], 2),
        ]), $byCode));
    }

    /**
     * Somme des charges patronales additionnelles par code.
     *
     * @return array<int, array{code: string, label: string, rate: float|null, amount: float}>
     */
    public function aggregateEmployerChargeLines($records): array
    {
        $byCode = [];

        foreach ($records as $record) {
            foreach ($record->additionalEmployerChargeLines() as $line) {
                $code = $line['code'];
                $byCode[$code] ??= ['code' => $code, 'label' => $line['label'], 'rate' => $line['rate'], 'amount' => 0.0];
                $byCode[$code]['amount'] = round($byCode[$code]['amount'] + $line['amount'], 2);
            }
        }

        return array_values($byCode);
    }

    /**
     * Versions de paramètres utilisées par les bulletins (traçabilité).
     *
     * @return array<int, int|null>
     */
    protected function settingsVersionsUsed($records): array
    {
        return $records
            ->map(fn ($record) => $record->deductions_breakdown['settings_version'] ?? null)
            ->unique()
            ->values()
            ->all();
    }

    protected function incomeTaxLabel($records): string
    {
        foreach ($records as $record) {
            $label = $record->deductions_breakdown['income_tax']['label'] ?? null;

            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        return 'Impôt sur salaires';
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
        // Échéances indicatives (à adapter au calendrier des organismes) : le 15 du mois suivant.
        return $period->end_date->copy()->addDays(15);
    }

    /**
     * Generate unique declaration number
     */
    protected function generateDeclarationNumber(string $type, PayrollPeriod $period): string
    {
        $prefix = match ($type) {
            self::TYPE_SOCIAL_CONTRIBUTIONS => 'COTIS',
            self::TYPE_INCOME_TAX => 'IMPOT',
            self::TYPE_EMPLOYER_CHARGES => 'CHPAT',
            default => strtoupper(substr($type, 0, 5)),
        };
        $yearMonth = $period->period_code;

        $count = SocialDeclaration::on('tenant')
            ->where('declaration_type', $type)
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->count();

        return sprintf('%s-%s-%02d', $prefix, $yearMonth, $count + 1);
    }
}
