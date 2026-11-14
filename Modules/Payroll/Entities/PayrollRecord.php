<?php

namespace Modules\Payroll\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollRecord extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'payroll_records';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'contract_id',
        'base_salary',
        'days_worked',
        'days_absent',
        'hours_worked',
        'overtime_hours',
        'bonuses',
        'allowances',
        'overtime_pay',
        'commissions',
        'other_earnings',
        'total_earnings',
        'gross_salary',
        'cnss_employee',
        'cimr_employee',
        'amo_employee',
        'income_tax',
        'advance_deductions',
        'loan_deductions',
        'other_deductions',
        'total_deductions',
        'net_salary',
        'net_taxable',
        'cnss_employer',
        'cimr_employer',
        'amo_employer',
        'professional_tax',
        'training_tax',
        'total_employer_charges',
        'total_cost',
        'earnings_breakdown',
        'deductions_breakdown',
        'charges_breakdown',
        'payment_status',
        'payment_date',
        'payment_method',
        'payment_reference',
        'status',
        'is_locked',
        'notes',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'days_worked' => 'integer',
            'days_absent' => 'integer',
            'hours_worked' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'bonuses' => 'decimal:2',
            'allowances' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'commissions' => 'decimal:2',
            'other_earnings' => 'decimal:2',
            'total_earnings' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'cnss_employee' => 'decimal:2',
            'cimr_employee' => 'decimal:2',
            'amo_employee' => 'decimal:2',
            'income_tax' => 'decimal:2',
            'advance_deductions' => 'decimal:2',
            'loan_deductions' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'net_taxable' => 'decimal:2',
            'cnss_employer' => 'decimal:2',
            'cimr_employer' => 'decimal:2',
            'amo_employer' => 'decimal:2',
            'professional_tax' => 'decimal:2',
            'training_tax' => 'decimal:2',
            'total_employer_charges' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'earnings_breakdown' => 'array',
            'deductions_breakdown' => 'array',
            'charges_breakdown' => 'array',
            'payment_date' => 'date',
            'is_locked' => 'boolean',
        ];
    }

    /**
     * Relations
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(EmploymentContract::class);
    }

    public function payslip(): HasOne
    {
        return $this->hasOne(Payslip::class);
    }

    /**
     * Scopes
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeCalculated($query)
    {
        return $query->where('status', 'calculated');
    }

    public function scopeValidated($query)
    {
        return $query->where('status', 'validated');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }

    public function scopeUnlocked($query)
    {
        return $query->where('is_locked', false);
    }

    /**
     * Cotisations sociales par code (salarié + employeur).
     * Bulletins calculés avant le paramétrage : une ligne globale « calcul antérieur ».
     *
     * @return array<int, array{code: string, label: string, base: float, employee_rate: float|null, employer_rate: float|null, employee_amount: float, employer_amount: float}>
     */
    public function contributionLines(): array
    {
        $deductions = $this->deductions_breakdown ?? [];
        $charges = $this->charges_breakdown ?? [];

        if (($deductions['format'] ?? null) === 'configurable') {
            $employerByCode = collect($charges['contributions'] ?? [])->keyBy('code');

            return array_map(function (array $line) use ($employerByCode): array {
                $employer = $employerByCode->get($line['code'], []);

                return [
                    'code' => (string) $line['code'],
                    'label' => (string) $line['label'],
                    'base' => (float) ($line['base'] ?? 0),
                    'employee_rate' => isset($line['rate']) ? (float) $line['rate'] : null,
                    'employer_rate' => isset($employer['rate']) ? (float) $employer['rate'] : null,
                    'employee_amount' => (float) ($line['amount'] ?? 0),
                    'employer_amount' => (float) ($employer['amount'] ?? 0),
                ];
            }, $deductions['contributions'] ?? []);
        }

        $employeeAmount = (float) $this->cnss_employee + (float) $this->amo_employee + (float) $this->cimr_employee;
        $employerAmount = (float) $this->cnss_employer + (float) $this->amo_employer + (float) $this->cimr_employer;

        if ($employeeAmount == 0.0 && $employerAmount == 0.0) {
            return [];
        }

        return [[
            'code' => 'legacy_social',
            'label' => 'Cotisations sociales (calcul antérieur au paramétrage)',
            'base' => (float) $this->base_salary,
            'employee_rate' => null,
            'employer_rate' => null,
            'employee_amount' => $employeeAmount,
            'employer_amount' => $employerAmount,
        ]];
    }

    /**
     * Charges patronales additionnelles (taxes assises sur la masse salariale) par code.
     *
     * @return array<int, array{code: string, label: string, rate: float|null, amount: float}>
     */
    public function additionalEmployerChargeLines(): array
    {
        $charges = $this->charges_breakdown ?? [];

        if (($charges['format'] ?? null) === 'configurable') {
            return array_map(fn (array $line): array => [
                'code' => (string) $line['code'],
                'label' => (string) $line['label'],
                'rate' => isset($line['rate']) ? (float) $line['rate'] : null,
                'amount' => (float) ($line['amount'] ?? 0),
            ], $charges['employer_charges'] ?? []);
        }

        $amount = (float) $this->professional_tax + (float) $this->training_tax;

        return $amount == 0.0 ? [] : [[
            'code' => 'legacy_payroll_taxes',
            'label' => 'Taxes sur salaires (calcul antérieur au paramétrage)',
            'rate' => null,
            'amount' => $amount,
        ]];
    }

    /**
     * Business Methods
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isCalculated(): bool
    {
        return in_array($this->status, ['calculated', 'validated', 'paid']);
    }

    public function isValidated(): bool
    {
        return in_array($this->status, ['validated', 'paid']);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isLocked(): bool
    {
        return $this->is_locked === true;
    }

    public function canBeModified(): bool
    {
        return ! $this->isLocked() && in_array($this->status, ['draft', 'calculated']);
    }

    public function lock(): bool
    {
        $this->is_locked = true;

        return $this->save();
    }

    public function unlock(): bool
    {
        $this->is_locked = false;

        return $this->save();
    }
}
