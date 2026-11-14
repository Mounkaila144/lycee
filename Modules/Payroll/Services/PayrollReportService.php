<?php

namespace Modules\Payroll\Services;

use Illuminate\Support\Collection;
use Modules\Payroll\Entities\PayrollPeriod;
use Modules\Payroll\Entities\PayrollRecord;
use Modules\Payroll\Entities\SocialDeclaration;

/**
 * Epic 5: Rapports RH (Stories 18-20)
 *
 * Story 18: Generate payroll journal
 * Story 19: Generate social charges report
 * Story 20: Generate salary statistics
 */
class PayrollReportService
{
    public function __construct(
        protected SocialDeclarationService $declarationService
    ) {}

    /**
     * Story 18: Generate payroll journal
     */
    public function generatePayrollJournal(PayrollPeriod $period): array
    {
        $records = PayrollRecord::on('tenant')
            ->where('payroll_period_id', $period->id)
            ->with(['employee', 'contract'])
            ->orderBy('employee_id')
            ->get();

        $journal = [
            'period' => [
                'code' => $period->period_code,
                'name' => $period->name,
                'start_date' => $period->start_date->format('d/m/Y'),
                'end_date' => $period->end_date->format('d/m/Y'),
                'payment_date' => $period->payment_date->format('d/m/Y'),
                'status' => $period->status,
            ],
            'summary' => [
                'total_employees' => $period->total_employees,
                'total_gross_salary' => $period->total_gross_salary,
                'total_deductions' => $period->total_deductions,
                'total_net_salary' => $period->total_net_salary,
                'total_employer_charges' => $period->total_employer_charges,
                'total_cost' => $period->total_gross_salary + $period->total_employer_charges,
            ],
            'employees' => [],
            'totals_by_department' => $this->getTotalsByDepartment($records),
            'accounting_entries' => $this->generateAccountingEntries($period, $records),
        ];

        foreach ($records as $record) {
            $journal['employees'][] = [
                'employee_code' => $record->employee->employee_code,
                'full_name' => $record->employee->full_name,
                'department' => $record->employee->department,
                'position' => $record->employee->position,
                'base_salary' => $record->base_salary,
                'earnings' => [
                    'bonuses' => $record->bonuses,
                    'allowances' => $record->allowances,
                    'overtime' => $record->overtime_pay,
                    'total' => $record->total_earnings,
                ],
                'gross_salary' => $record->gross_salary,
                'deductions' => [
                    'social_contributions' => $record->cnss_employee + $record->amo_employee + $record->cimr_employee,
                    'income_tax' => $record->income_tax,
                    'advances' => $record->advance_deductions,
                    'other' => $record->other_deductions,
                    'total' => $record->total_deductions,
                ],
                'net_salary' => $record->net_salary,
                'employer_charges' => [
                    'social_contributions' => $record->cnss_employer + $record->amo_employer + $record->cimr_employer,
                    'additional_charges' => $record->professional_tax + $record->training_tax,
                    'total' => $record->total_employer_charges,
                ],
                'contributions' => $record->contributionLines(),
                'total_cost' => $record->total_cost,
            ];
        }

        return $journal;
    }

    /**
     * Story 19: Generate social charges report
     */
    public function getSocialChargesReport(PayrollPeriod $period): array
    {
        $records = $period->payrollRecords;

        $contributions = $this->declarationService->aggregateContributionLines($records);
        $employerCharges = $this->declarationService->aggregateEmployerChargeLines($records);
        $employeeContributions = round(array_sum(array_column($contributions, 'employee_amount')), 2);
        $employerContributions = round(array_sum(array_column($contributions, 'employer_amount')), 2);
        $additionalCharges = round(array_sum(array_column($employerCharges, 'amount')), 2);
        $incomeTax = round((float) $records->sum('income_tax'), 2);

        $report = [
            'period' => [
                'code' => $period->period_code,
                'name' => $period->name,
                'year' => $period->year,
                'month' => $period->month,
            ],
            'contributions' => $contributions,
            'employer_charges' => $employerCharges,
            'taxes' => [
                'income_tax' => $incomeTax,
                'employer_charges' => $additionalCharges,
                'total' => round($incomeTax + $additionalCharges, 2),
            ],
            'summary' => [
                'total_employee_contributions' => $employeeContributions,
                'total_employer_contributions' => $employerContributions,
                'total_additional_employer_charges' => $additionalCharges,
                'total_taxes' => $incomeTax,
                'grand_total' => round($employeeContributions + $employerContributions + $additionalCharges + $incomeTax, 2),
            ],
            'declarations' => $this->getDeclarationsSummary($period),
        ];

        return $report;
    }

    /**
     * Story 20: Generate salary statistics
     */
    public function getSalaryStatistics(?int $year = null, ?int $month = null): array
    {
        $year = $year ?? now()->year;

        // Get periods for analysis
        $query = PayrollPeriod::on('tenant')->forYear($year);
        if ($month) {
            $query->forMonth($month);
        }

        $periods = $query->validated()->get();

        if ($periods->isEmpty()) {
            return ['message' => 'Aucune donnée disponible'];
        }

        $allRecords = collect();
        foreach ($periods as $period) {
            $allRecords = $allRecords->merge($period->payrollRecords);
        }

        $statistics = [
            'period' => $month ? "{$month}/{$year}" : $year,
            'overview' => [
                'total_periods' => $periods->count(),
                'total_employees' => $allRecords->unique('employee_id')->count(),
                'average_employees_per_period' => round($periods->avg('total_employees'), 1),
                'total_payroll_cost' => $allRecords->sum('total_cost'),
                'average_monthly_cost' => round($allRecords->sum('total_cost') / max($periods->count(), 1), 2),
            ],
            'salary_distribution' => [
                'average_base_salary' => round($allRecords->avg('base_salary'), 2),
                'median_base_salary' => $this->calculateMedian($allRecords->pluck('base_salary')),
                'min_base_salary' => $allRecords->min('base_salary'),
                'max_base_salary' => $allRecords->max('base_salary'),
                'average_gross_salary' => round($allRecords->avg('gross_salary'), 2),
                'average_net_salary' => round($allRecords->avg('net_salary'), 2),
            ],
            'earnings' => [
                'total_bonuses' => $allRecords->sum('bonuses'),
                'average_bonuses' => round($allRecords->avg('bonuses'), 2),
                'total_allowances' => $allRecords->sum('allowances'),
                'average_allowances' => round($allRecords->avg('allowances'), 2),
                'total_overtime' => $allRecords->sum('overtime_pay'),
                'average_overtime' => round($allRecords->avg('overtime_pay'), 2),
            ],
            'deductions' => [
                'total_deductions' => $allRecords->sum('total_deductions'),
                'average_deductions' => round($allRecords->avg('total_deductions'), 2),
                'deduction_rate' => round(($allRecords->sum('total_deductions') / max($allRecords->sum('gross_salary'), 1)) * 100, 2),
            ],
            'by_department' => $this->getStatisticsByDepartment($allRecords),
            'by_position' => $this->getStatisticsByPosition($allRecords),
            'trends' => $this->getSalaryTrends($year),
        ];

        return $statistics;
    }

    /**
     * Get totals by department
     */
    protected function getTotalsByDepartment(Collection $records): array
    {
        $byDepartment = [];

        foreach ($records as $record) {
            $dept = $record->employee->department ?? 'Non défini';

            if (! isset($byDepartment[$dept])) {
                $byDepartment[$dept] = [
                    'count' => 0,
                    'total_gross' => 0,
                    'total_net' => 0,
                    'total_charges' => 0,
                ];
            }

            $byDepartment[$dept]['count']++;
            $byDepartment[$dept]['total_gross'] += $record->gross_salary;
            $byDepartment[$dept]['total_net'] += $record->net_salary;
            $byDepartment[$dept]['total_charges'] += $record->total_employer_charges;
        }

        return $byDepartment;
    }

    /**
     * Écritures comptables de la paie (plan SYSCOHADA, comptes indicatifs).
     */
    protected function generateAccountingEntries(PayrollPeriod $period, Collection $records): array
    {
        $socialContributions = $records->sum(fn ($record) => (float) $record->cnss_employee + (float) $record->amo_employee + (float) $record->cimr_employee
            + (float) $record->cnss_employer + (float) $record->amo_employer + (float) $record->cimr_employer);
        $additionalCharges = $records->sum(fn ($record) => (float) $record->professional_tax + (float) $record->training_tax);

        return [
            [
                'account' => '661',
                'label' => 'Rémunérations directes versées au personnel',
                'debit' => $period->total_gross_salary,
                'credit' => 0,
            ],
            [
                'account' => '664',
                'label' => 'Charges sociales et taxes patronales',
                'debit' => $period->total_employer_charges,
                'credit' => 0,
            ],
            [
                'account' => '431',
                'label' => 'Sécurité sociale — cotisations à payer',
                'debit' => 0,
                'credit' => $socialContributions,
            ],
            [
                'account' => '447',
                'label' => 'État — impôt sur salaires retenu à la source',
                'debit' => 0,
                'credit' => $records->sum('income_tax'),
            ],
            [
                'account' => '447',
                'label' => 'État — taxes patronales sur salaires',
                'debit' => 0,
                'credit' => $additionalCharges,
            ],
            [
                'account' => '422',
                'label' => 'Personnel — rémunérations dues',
                'debit' => 0,
                'credit' => $period->total_net_salary,
            ],
        ];
    }

    /**
     * Get declarations summary
     */
    protected function getDeclarationsSummary(PayrollPeriod $period): array
    {
        $declarations = SocialDeclaration::on('tenant')
            ->where('payroll_period_id', $period->id)
            ->get();

        $summary = [];
        foreach ($declarations as $declaration) {
            $summary[] = [
                'type' => $declaration->declaration_type,
                'number' => $declaration->declaration_number,
                'amount_due' => $declaration->total_amount_due,
                'status' => $declaration->status,
                'due_date' => $declaration->due_date?->format('d/m/Y'),
            ];
        }

        return $summary;
    }

    /**
     * Get statistics by department
     */
    protected function getStatisticsByDepartment(Collection $records): array
    {
        $stats = [];

        $grouped = $records->groupBy(function ($record) {
            return $record->employee->department ?? 'Non défini';
        });

        foreach ($grouped as $department => $deptRecords) {
            $stats[$department] = [
                'employee_count' => $deptRecords->unique('employee_id')->count(),
                'average_base_salary' => round($deptRecords->avg('base_salary'), 2),
                'average_gross_salary' => round($deptRecords->avg('gross_salary'), 2),
                'average_net_salary' => round($deptRecords->avg('net_salary'), 2),
                'total_cost' => $deptRecords->sum('total_cost'),
            ];
        }

        return $stats;
    }

    /**
     * Get statistics by position
     */
    protected function getStatisticsByPosition(Collection $records): array
    {
        $stats = [];

        $grouped = $records->groupBy(function ($record) {
            return $record->employee->position ?? 'Non défini';
        });

        foreach ($grouped as $position => $posRecords) {
            $stats[$position] = [
                'employee_count' => $posRecords->unique('employee_id')->count(),
                'average_base_salary' => round($posRecords->avg('base_salary'), 2),
                'average_gross_salary' => round($posRecords->avg('gross_salary'), 2),
                'min_salary' => $posRecords->min('base_salary'),
                'max_salary' => $posRecords->max('base_salary'),
            ];
        }

        return $stats;
    }

    /**
     * Get salary trends over time
     */
    protected function getSalaryTrends(int $year): array
    {
        $periods = PayrollPeriod::on('tenant')
            ->forYear($year)
            ->validated()
            ->orderBy('month')
            ->get();

        $trends = [];
        foreach ($periods as $period) {
            $trends[] = [
                'period' => $period->period_code,
                'month' => $period->month,
                'total_employees' => $period->total_employees,
                'average_gross' => round($period->total_gross_salary / max($period->total_employees, 1), 2),
                'average_net' => round($period->total_net_salary / max($period->total_employees, 1), 2),
                'total_cost' => $period->total_gross_salary + $period->total_employer_charges,
            ];
        }

        return $trends;
    }

    /**
     * Calculate median value
     */
    protected function calculateMedian(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $count = $sorted->count();

        if ($count === 0) {
            return 0;
        }

        $middle = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return ($sorted[$middle - 1] + $sorted[$middle]) / 2;
        }

        return $sorted[$middle];
    }
}
