<?php

namespace Modules\Payroll\Services;

/**
 * Moteur de calcul de paie paramétrable (aucun taux codé en dur).
 *
 * brut → assiettes plafonnées → cotisations salariales et patronales par ligne
 * → base imposable → impôt par tranches → net ; coût employeur.
 */
class PayrollCalculator
{
    /**
     * @param  array<string, mixed>  $settings  Paramètres normalisés (cf. PayrollSettingsService::normalize()).
     * @return array{
     *     currency_label: string,
     *     gross_salary: float,
     *     contributions: array<int, array{code: string, label: string, base: float, employee_rate: float, employer_rate: float, employee_amount: float, employer_amount: float, tax_deductible: bool, ceiling: float|null, floor: float|null}>,
     *     total_employee_contributions: float,
     *     total_employer_contributions: float,
     *     deductible_contributions: float,
     *     taxable_before_allowance: float,
     *     flat_allowance_rate: float,
     *     flat_allowance_amount: float,
     *     taxable_income: float,
     *     income_tax_label: string,
     *     income_tax_brackets: array<int, array{from: float, to: float|null, rate: float, base: float, amount: float}>,
     *     income_tax: float,
     *     employer_charges: array<int, array{code: string, label: string, rate: float, base: float, amount: float}>,
     *     total_additional_employer_charges: float,
     *     total_employer_charges: float,
     *     net_salary: float,
     *     employer_cost: float
     * }
     */
    public function calculate(float $grossSalary, array $settings): array
    {
        $precision = (int) ($settings['rounding_precision'] ?? 0);
        $gross = round(max(0, $grossSalary), $precision);

        $contributionLines = [];
        $totalEmployee = 0.0;
        $totalEmployer = 0.0;
        $deductible = 0.0;

        foreach ($settings['contributions'] ?? [] as $contribution) {
            if (! ($contribution['active'] ?? true)) {
                continue;
            }

            $base = $this->contributionBase($gross, $contribution['ceiling'] ?? null, $contribution['floor'] ?? null);
            $employeeAmount = round($base * (float) $contribution['employee_rate'] / 100, $precision);
            $employerAmount = round($base * (float) $contribution['employer_rate'] / 100, $precision);

            $contributionLines[] = [
                'code' => (string) $contribution['code'],
                'label' => (string) $contribution['label'],
                'base' => $base,
                'employee_rate' => (float) $contribution['employee_rate'],
                'employer_rate' => (float) $contribution['employer_rate'],
                'employee_amount' => $employeeAmount,
                'employer_amount' => $employerAmount,
                'tax_deductible' => (bool) ($contribution['tax_deductible'] ?? false),
                'ceiling' => isset($contribution['ceiling']) ? (float) $contribution['ceiling'] : null,
                'floor' => isset($contribution['floor']) ? (float) $contribution['floor'] : null,
            ];

            $totalEmployee += $employeeAmount;
            $totalEmployer += $employerAmount;

            if ($contribution['tax_deductible'] ?? false) {
                $deductible += $employeeAmount;
            }
        }

        $incomeTaxSettings = $settings['income_tax'] ?? [];
        $deductContributions = (bool) ($incomeTaxSettings['deduct_employee_contributions'] ?? false);
        $taxableBeforeAllowance = max(0, $gross - ($deductContributions ? $deductible : 0));
        $flatAllowanceRate = (float) ($incomeTaxSettings['flat_allowance_rate'] ?? 0);
        $flatAllowance = round($taxableBeforeAllowance * $flatAllowanceRate / 100, $precision);
        $taxableIncome = max(0, $taxableBeforeAllowance - $flatAllowance);

        $bracketLines = [];
        $incomeTax = 0.0;

        foreach ($incomeTaxSettings['brackets'] ?? [] as $bracket) {
            $from = (float) $bracket['from'];
            $to = isset($bracket['to']) ? (float) $bracket['to'] : null;
            $portion = max(0, min($taxableIncome, $to ?? $taxableIncome) - $from);
            $amount = $portion * (float) $bracket['rate'] / 100;

            $bracketLines[] = [
                'from' => $from,
                'to' => $to,
                'rate' => (float) $bracket['rate'],
                'base' => round($portion, 2),
                'amount' => round($amount, 2),
            ];

            $incomeTax += $amount;
        }

        $incomeTax = round($incomeTax, $precision);

        $chargeLines = [];
        $totalAdditional = 0.0;

        foreach ($settings['employer_charges'] ?? [] as $charge) {
            if (! ($charge['active'] ?? true)) {
                continue;
            }

            $amount = round($gross * (float) $charge['rate'] / 100, $precision);
            $chargeLines[] = [
                'code' => (string) $charge['code'],
                'label' => (string) $charge['label'],
                'rate' => (float) $charge['rate'],
                'base' => $gross,
                'amount' => $amount,
            ];
            $totalAdditional += $amount;
        }

        $totalEmployerCharges = $totalEmployer + $totalAdditional;

        return [
            'currency_label' => (string) ($settings['currency_label'] ?? ''),
            'gross_salary' => $gross,
            'contributions' => $contributionLines,
            'total_employee_contributions' => $totalEmployee,
            'total_employer_contributions' => $totalEmployer,
            'deductible_contributions' => $deductContributions ? $deductible : 0.0,
            'taxable_before_allowance' => $taxableBeforeAllowance,
            'flat_allowance_rate' => $flatAllowanceRate,
            'flat_allowance_amount' => $flatAllowance,
            'taxable_income' => $taxableIncome,
            'income_tax_label' => (string) ($incomeTaxSettings['label'] ?? 'Impôt sur salaire'),
            'income_tax_brackets' => $bracketLines,
            'income_tax' => $incomeTax,
            'employer_charges' => $chargeLines,
            'total_additional_employer_charges' => $totalAdditional,
            'total_employer_charges' => $totalEmployerCharges,
            'net_salary' => $gross - $totalEmployee - $incomeTax,
            'employer_cost' => $gross + $totalEmployerCharges,
        ];
    }

    /**
     * Assiette de cotisation : brut borné par le plancher et le plafond mensuels.
     */
    protected function contributionBase(float $gross, mixed $ceiling, mixed $floor): float
    {
        $base = $gross;

        if ($floor !== null && $floor !== '' && $base < (float) $floor) {
            $base = (float) $floor;
        }

        if ($ceiling !== null && $ceiling !== '' && $base > (float) $ceiling) {
            $base = (float) $ceiling;
        }

        return $base;
    }
}
