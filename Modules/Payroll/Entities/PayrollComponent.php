<?php

namespace Modules\Payroll\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollComponent extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'payroll_components';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'employee_id',
        'code',
        'name',
        'description',
        'component_type',
        'category',
        'calculation_type',
        'amount',
        'percentage',
        'rate',
        'is_taxable',
        'is_subject_to_cnss',
        'is_subject_to_cimr',
        'frequency',
        'is_recurring',
        'valid_from',
        'valid_to',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'percentage' => 'decimal:2',
            'rate' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_subject_to_cnss' => 'boolean',
            'is_subject_to_cimr' => 'boolean',
            'is_recurring' => 'boolean',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'approved_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('component_type', $type);
    }

    public function scopeBonuses($query)
    {
        return $query->where('component_type', 'bonus');
    }

    public function scopeDeductions($query)
    {
        return $query->where('component_type', 'deduction');
    }

    public function scopeOvertime($query)
    {
        return $query->where('component_type', 'overtime');
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    public function scopeOneTime($query)
    {
        return $query->where('is_recurring', false);
    }

    public function scopeValid($query, ?\Carbon\Carbon $date = null)
    {
        $date = $date ?? now();

        return $query->where(function ($q) use ($date) {
            $q->whereNull('valid_from')
                ->orWhere('valid_from', '<=', $date);
        })->where(function ($q) use ($date) {
            $q->whereNull('valid_to')
                ->orWhere('valid_to', '>=', $date);
        });
    }

    /**
     * Business Methods
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isBonus(): bool
    {
        return $this->component_type === 'bonus';
    }

    public function isDeduction(): bool
    {
        return $this->component_type === 'deduction';
    }

    public function isOvertime(): bool
    {
        return $this->component_type === 'overtime';
    }

    public function isValid(?\Carbon\Carbon $date = null): bool
    {
        $date = $date ?? now();

        if ($this->valid_from && $this->valid_from->gt($date)) {
            return false;
        }

        if ($this->valid_to && $this->valid_to->lt($date)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate component amount based on base salary
     */
    public function calculateAmount(float $baseSalary, ?float $hours = null): float
    {
        return match ($this->calculation_type) {
            'fixed' => $this->amount ?? 0,
            'percentage' => $baseSalary * ($this->percentage / 100),
            'hourly' => ($this->rate ?? 0) * ($hours ?? 0),
            'daily' => ($this->rate ?? 0) * ($hours ?? 0),
            default => 0,
        };
    }
}
