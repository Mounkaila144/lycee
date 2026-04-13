<?php

namespace Modules\Payroll\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'employees';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'employee_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'cin',
        'cnss_number',
        'date_of_birth',
        'place_of_birth',
        'gender',
        'marital_status',
        'number_of_dependents',
        'address',
        'city',
        'postal_code',
        'hire_date',
        'termination_date',
        'department',
        'position',
        'job_title',
        'bank_name',
        'bank_account_number',
        'rib',
        'tax_id',
        'tax_residence',
        'status',
        'termination_reason',
        'profile_picture',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'termination_date' => 'date',
            'number_of_dependents' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class)->orderBy('start_date', 'desc');
    }

    public function activeContract(): ?\Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EmploymentContract::class)->where('status', 'active')->latest('start_date');
    }

    public function payrollRecords(): HasMany
    {
        return $this->hasMany(PayrollRecord::class)->orderBy('created_at', 'desc');
    }

    public function payrollComponents(): HasMany
    {
        return $this->hasMany(PayrollComponent::class)->where('status', 'active');
    }

    public function advances(): HasMany
    {
        return $this->hasMany(EmployeeAdvance::class)->orderBy('request_date', 'desc');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class)->orderBy('issue_date', 'desc');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeTerminated($query)
    {
        return $query->where('status', 'terminated');
    }

    public function scopeByDepartment($query, string $department)
    {
        return $query->where('department', $department);
    }

    public function scopeByPosition($query, string $position)
    {
        return $query->where('position', $position);
    }

    public function scopeHiredBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('hire_date', [$startDate, $endDate]);
    }

    /**
     * Accessors
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function getYearsOfServiceAttribute(): ?int
    {
        if (! $this->hire_date) {
            return null;
        }

        $endDate = $this->termination_date ?? now();

        return $this->hire_date->diffInYears($endDate);
    }

    /**
     * Business Methods
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTerminated(): bool
    {
        return $this->status === 'terminated';
    }

    public function hasActiveContract(): bool
    {
        return $this->contracts()->where('status', 'active')->exists();
    }

    public function getCurrentContract(): ?EmploymentContract
    {
        return $this->contracts()->where('status', 'active')->latest('start_date')->first();
    }

    public function canBeTerminated(): bool
    {
        return in_array($this->status, ['active', 'suspended']);
    }

    public function getTotalAdvanceBalance(): float
    {
        return $this->advances()
            ->whereIn('status', ['disbursed', 'repaying'])
            ->sum('balance');
    }
}
