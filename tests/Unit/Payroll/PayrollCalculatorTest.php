<?php

namespace Tests\Unit\Payroll;

use Modules\Payroll\Services\PayrollCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Moteur de paie paramétrable : assiettes plafonnées, cotisations par ligne,
 * impôt par tranches, abattement, net et coût employeur.
 */
class PayrollCalculatorTest extends TestCase
{
    private PayrollCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PayrollCalculator;
    }

    /**
     * @param  array<int, array<string, mixed>>  $contributions
     * @param  array<int, array<string, mixed>>  $employerCharges
     * @return array<string, mixed>
     */
    private function settings(array $contributions = [], array $incomeTax = [], array $employerCharges = []): array
    {
        return [
            'currency_label' => 'FCFA',
            'rounding_precision' => 0,
            'contributions' => $contributions,
            'income_tax' => array_merge([
                'label' => 'IUTS',
                'brackets' => [['from' => 0, 'to' => null, 'rate' => 0]],
                'flat_allowance_rate' => 0,
                'deduct_employee_contributions' => true,
            ], $incomeTax),
            'employer_charges' => $employerCharges,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contribution(array $overrides = []): array
    {
        return array_merge([
            'code' => 'pension',
            'label' => 'Retraite',
            'employee_rate' => 5.25,
            'employer_rate' => 6.25,
            'ceiling' => 500000,
            'floor' => null,
            'active' => true,
            'tax_deductible' => true,
        ], $overrides);
    }

    #[Test]
    public function the_contribution_base_is_capped_at_the_monthly_ceiling(): void
    {
        $result = $this->calculator->calculate(800000, $this->settings([$this->contribution()]));

        $line = $result['contributions'][0];
        $this->assertSame(500000.0, $line['base']);
        $this->assertSame(26250.0, $line['employee_amount']);
        $this->assertSame(31250.0, $line['employer_amount']);
    }

    #[Test]
    public function the_contribution_base_is_raised_to_the_floor(): void
    {
        $result = $this->calculator->calculate(30000, $this->settings([$this->contribution(['floor' => 50000])]));

        $this->assertSame(50000.0, $result['contributions'][0]['base']);
        $this->assertSame(2625.0, $result['contributions'][0]['employee_amount']);
    }

    #[Test]
    public function a_contribution_without_ceiling_uses_the_full_gross(): void
    {
        $result = $this->calculator->calculate(800000, $this->settings([$this->contribution(['ceiling' => null, 'employer_rate' => 8.4, 'employee_rate' => 0])]));

        $this->assertSame(800000.0, $result['contributions'][0]['base']);
        $this->assertSame(67200.0, $result['contributions'][0]['employer_amount']);
        $this->assertSame(0.0, $result['contributions'][0]['employee_amount']);
    }

    #[Test]
    public function inactive_contributions_are_ignored(): void
    {
        $result = $this->calculator->calculate(100000, $this->settings([$this->contribution(['active' => false])]));

        $this->assertSame([], $result['contributions']);
        $this->assertSame(100000.0, $result['net_salary']);
    }

    #[Test]
    public function income_tax_is_computed_by_progressive_brackets(): void
    {
        $brackets = [
            ['from' => 0, 'to' => 25000, 'rate' => 0],
            ['from' => 25000, 'to' => 50000, 'rate' => 2],
            ['from' => 50000, 'to' => 100000, 'rate' => 6],
            ['from' => 100000, 'to' => null, 'rate' => 13],
        ];

        $result = $this->calculator->calculate(120000, $this->settings([], ['brackets' => $brackets]));

        // 0 + 25 000 × 2 % + 50 000 × 6 % + 20 000 × 13 % = 500 + 3 000 + 2 600
        $this->assertSame(6100.0, $result['income_tax']);
        $this->assertSame(120000.0, $result['taxable_income']);
        $this->assertSame(2600.0, $result['income_tax_brackets'][3]['amount']);
        $this->assertSame(113900.0, $result['net_salary']);
    }

    #[Test]
    public function deductible_employee_contributions_reduce_the_taxable_base(): void
    {
        $contributions = [
            $this->contribution(['code' => 'deductible', 'employee_rate' => 10, 'employer_rate' => 0, 'ceiling' => null, 'tax_deductible' => true]),
            $this->contribution(['code' => 'not_deductible', 'employee_rate' => 5, 'employer_rate' => 0, 'ceiling' => null, 'tax_deductible' => false]),
        ];
        $flatTax = ['brackets' => [['from' => 0, 'to' => null, 'rate' => 10]]];

        $result = $this->calculator->calculate(100000, $this->settings($contributions, $flatTax));

        $this->assertSame(10000.0, $result['deductible_contributions']);
        $this->assertSame(90000.0, $result['taxable_income']);
        $this->assertSame(9000.0, $result['income_tax']);
        $this->assertSame(100000.0 - 10000 - 5000 - 9000, $result['net_salary']);
    }

    #[Test]
    public function contributions_are_not_deducted_when_the_option_is_disabled(): void
    {
        $contributions = [$this->contribution(['employee_rate' => 10, 'ceiling' => null, 'tax_deductible' => true])];
        $tax = ['brackets' => [['from' => 0, 'to' => null, 'rate' => 10]], 'deduct_employee_contributions' => false];

        $result = $this->calculator->calculate(100000, $this->settings($contributions, $tax));

        $this->assertSame(100000.0, $result['taxable_income']);
        $this->assertSame(10000.0, $result['income_tax']);
    }

    #[Test]
    public function the_flat_allowance_is_applied_before_the_brackets(): void
    {
        $tax = ['brackets' => [['from' => 0, 'to' => null, 'rate' => 10]], 'flat_allowance_rate' => 20];

        $result = $this->calculator->calculate(100000, $this->settings([], $tax));

        $this->assertSame(20000.0, $result['flat_allowance_amount']);
        $this->assertSame(80000.0, $result['taxable_income']);
        $this->assertSame(8000.0, $result['income_tax']);
    }

    #[Test]
    public function employer_cost_includes_employer_contributions_and_additional_charges(): void
    {
        $charges = [
            ['code' => 'apprenticeship', 'label' => "Taxe d'apprentissage", 'rate' => 3, 'active' => true],
            ['code' => 'inactive', 'label' => 'Inactive', 'rate' => 50, 'active' => false],
        ];

        $result = $this->calculator->calculate(200000, $this->settings([$this->contribution()], [], $charges));

        $this->assertSame(12500.0, $result['total_employer_contributions']);
        $this->assertSame(6000.0, $result['total_additional_employer_charges']);
        $this->assertSame(18500.0, $result['total_employer_charges']);
        $this->assertSame(218500.0, $result['employer_cost']);
        $this->assertCount(1, $result['employer_charges']);
    }

    #[Test]
    public function amounts_are_rounded_to_the_currency_unit(): void
    {
        $result = $this->calculator->calculate(123457, $this->settings([$this->contribution()]));

        // 123 457 × 5,25 % = 6 481,49 → 6 481
        $this->assertSame(6481.0, $result['contributions'][0]['employee_amount']);
        $this->assertSame(7716.0, $result['contributions'][0]['employer_amount']);
    }
}
