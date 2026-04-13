<?php

namespace Modules\Payroll\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollPeriod extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'payroll_periods';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'period_code',
        'name',
        'period_type',
        'start_date',
        'end_date',
        'year',
        'month',
        'payment_date',
        'cutoff_date',
        'status',
        'total_employees',
        'total_gross_salary',
        'total_deductions',
        'total_net_salary',
        'total_employer_charges',
        'calculated_by',
        'calculated_at',
        'validated_by',
        'validated_at',
        'closed_by',
        'closed_at',
        'notes',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'payment_date' => 'date',
            'cutoff_date' => 'date',
            'year' => 'integer',
            'month' => 'integer',
            'total_employees' => 'integer',
            'total_gross_salary' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net_salary' => 'decimal:2',
            'total_employer_charges' => 'decimal:2',
            'calculated_at' => 'datetime',
            'validated_at' => 'datetime',
            'closed_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function payrollRecords(): HasMany
    {
        return $this->hasMany(PayrollRecord::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function socialDeclarations(): HasMany
    {
        return $this->hasMany(SocialDeclaration::class);
    }

    /**
     * Scopes
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
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

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeForMonth($query, int $month)
    {
        return $query->where('month', $month);
    }

    public function scopeForYearMonth($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
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
        return in_array($this->status, ['calculated', 'validated', 'paid', 'closed']);
    }

    public function isValidated(): bool
    {
        return in_array($this->status, ['validated', 'paid', 'closed']);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'closed']);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function canBeModified(): bool
    {
        return in_array($this->status, ['draft', 'in_progress']);
    }

    public function canBeCalculated(): bool
    {
        return in_array($this->status, ['draft', 'in_progress']);
    }

    public function canBeValidated(): bool
    {
        return $this->status === 'calculated';
    }

    public function canBePaid(): bool
    {
        return $this->status === 'validated';
    }

    public function canBeClosed(): bool
    {
        return $this->status === 'paid';
    }

    public function canBeDeleted(): bool
    {
        return in_array($this->status, ['draft', 'in_progress']);
    }

    public function updateTotals(): void
    {
        $records = $this->payrollRecords;

        $this->total_employees = $records->count();
        $this->total_gross_salary = $records->sum('gross_salary');
        $this->total_deductions = $records->sum('total_deductions');
        $this->total_net_salary = $records->sum('net_salary');
        $this->total_employer_charges = $records->sum('total_employer_charges');

        $this->save();
    }
}
