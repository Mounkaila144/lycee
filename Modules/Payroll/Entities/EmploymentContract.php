<?php

namespace Modules\Payroll\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmploymentContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'employment_contracts';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'employee_id',
        'contract_number',
        'contract_type',
        'start_date',
        'end_date',
        'probation_end_date',
        'weekly_hours',
        'work_schedule',
        'base_salary',
        'salary_scale_id',
        'salary_scale_grade',
        'benefits',
        'contract_terms',
        'job_description',
        'contract_document',
        'status',
        'signature_date',
        'signed_by',
        'is_renewable',
        'renewed_from_contract_id',
        'renewed_to_contract_id',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'probation_end_date' => 'date',
            'signature_date' => 'date',
            'weekly_hours' => 'integer',
            'base_salary' => 'decimal:2',
            'benefits' => 'array',
            'is_renewable' => 'boolean',
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

    public function salaryScale(): BelongsTo
    {
        return $this->belongsTo(SalaryScale::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ContractAmendment::class, 'contract_id')->orderBy('effective_date', 'desc');
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(EmploymentContract::class, 'renewed_from_contract_id');
    }

    public function renewedTo(): BelongsTo
    {
        return $this->belongsTo(EmploymentContract::class, 'renewed_to_contract_id');
    }

    public function payrollRecords(): HasMany
    {
        return $this->hasMany(PayrollRecord::class, 'contract_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<=', now()->addDays($days));
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('contract_type', $type);
    }

    public function scopePermanent($query)
    {
        return $query->where('contract_type', 'permanent');
    }

    public function scopeFixedTerm($query)
    {
        return $query->where('contract_type', 'fixed_term');
    }

    /**
     * Business Methods
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->end_date && $this->end_date->isPast());
    }

    public function isPermanent(): bool
    {
        return $this->contract_type === 'permanent';
    }

    public function isFixedTerm(): bool
    {
        return $this->contract_type === 'fixed_term';
    }

    public function isInProbationPeriod(): bool
    {
        return $this->probation_end_date && now()->lte($this->probation_end_date);
    }

    public function canBeAmended(): bool
    {
        return $this->status === 'active';
    }

    public function canBeRenewed(): bool
    {
        return $this->is_renewable
            && $this->isFixedTerm()
            && ($this->status === 'active' || $this->status === 'expired')
            && ! $this->renewed_to_contract_id;
    }

    public function getDurationInMonths(): ?int
    {
        if (! $this->end_date) {
            return null;
        }

        return $this->start_date->diffInMonths($this->end_date);
    }

    public function getRemainingDays(): ?int
    {
        if (! $this->end_date || $this->isExpired()) {
            return null;
        }

        return now()->diffInDays($this->end_date);
    }
}
