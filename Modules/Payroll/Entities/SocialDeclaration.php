<?php

namespace Modules\Payroll\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialDeclaration extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'social_declarations';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'payroll_period_id',
        'year',
        'month',
        'period_type',
        'declaration_type',
        'declaration_number',
        'declaration_date',
        'due_date',
        'employer_name',
        'employer_ice',
        'employer_cnss',
        'employer_tax_id',
        'total_employees',
        'total_gross_salary',
        'total_taxable_salary',
        'total_employee_contributions',
        'total_employer_contributions',
        'total_amount_due',
        'cnss_employee_rate',
        'cnss_employer_rate',
        'cnss_employee_amount',
        'cnss_employer_amount',
        'amo_employee_rate',
        'amo_employer_rate',
        'amo_employee_amount',
        'amo_employer_amount',
        'income_tax_withheld',
        'professional_tax_amount',
        'training_tax_amount',
        'employee_details',
        'calculation_details',
        'declaration_file',
        'supporting_documents',
        'status',
        'submission_date',
        'submission_reference',
        'submission_response',
        'payment_date',
        'payment_reference',
        'payment_amount',
        'payment_method',
        'prepared_by',
        'prepared_at',
        'validated_by',
        'validated_at',
        'validation_notes',
        'late_penalty',
        'adjustments',
        'adjustment_reason',
        'notes',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'declaration_date' => 'date',
            'due_date' => 'date',
            'submission_date' => 'date',
            'payment_date' => 'date',
            'prepared_at' => 'datetime',
            'validated_at' => 'datetime',
            'total_employees' => 'integer',
            'total_gross_salary' => 'decimal:2',
            'total_taxable_salary' => 'decimal:2',
            'total_employee_contributions' => 'decimal:2',
            'total_employer_contributions' => 'decimal:2',
            'total_amount_due' => 'decimal:2',
            'cnss_employee_rate' => 'decimal:2',
            'cnss_employer_rate' => 'decimal:2',
            'cnss_employee_amount' => 'decimal:2',
            'cnss_employer_amount' => 'decimal:2',
            'amo_employee_rate' => 'decimal:2',
            'amo_employer_rate' => 'decimal:2',
            'amo_employee_amount' => 'decimal:2',
            'amo_employer_amount' => 'decimal:2',
            'income_tax_withheld' => 'decimal:2',
            'professional_tax_amount' => 'decimal:2',
            'training_tax_amount' => 'decimal:2',
            'payment_amount' => 'decimal:2',
            'late_penalty' => 'decimal:2',
            'adjustments' => 'decimal:2',
            'employee_details' => 'array',
            'calculation_details' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    /**
     * Scopes
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeValidated($query)
    {
        return $query->where('status', 'validated');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('declaration_type', $type);
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

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['paid', 'accepted']);
    }

    /**
     * Business Methods
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isValidated(): bool
    {
        return in_array($this->status, ['validated', 'submitted', 'accepted', 'paid']);
    }

    public function isSubmitted(): bool
    {
        return in_array($this->status, ['submitted', 'accepted', 'paid']);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && ! $this->isPaid();
    }

    public function canBeValidated(): bool
    {
        return $this->status === 'draft';
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === 'validated';
    }

    public function canBePaid(): bool
    {
        return in_array($this->status, ['validated', 'submitted', 'accepted']);
    }

    public function getDaysUntilDue(): ?int
    {
        if (! $this->due_date || $this->isPaid()) {
            return null;
        }

        return now()->diffInDays($this->due_date, false);
    }
}
