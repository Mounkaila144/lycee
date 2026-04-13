<?php

namespace Modules\Payroll\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryScale extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'salary_scales';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'grades',
        'effective_from',
        'effective_to',
        'is_active',
        'approved_by',
        'approved_at',
    ];

    /**
     * Laravel 12: casts() method
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grades' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeEffective($query, ?\Carbon\Carbon $date = null)
    {
        $date = $date ?? now();

        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            });
    }

    /**
     * Business Methods
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function isEffective(?\Carbon\Carbon $date = null): bool
    {
        $date = $date ?? now();

        if ($this->effective_from->gt($date)) {
            return false;
        }

        if ($this->effective_to && $this->effective_to->lt($date)) {
            return false;
        }

        return true;
    }

    /**
     * Get salary for a specific grade
     */
    public function getSalaryForGrade(string $grade): ?array
    {
        foreach ($this->grades as $gradeData) {
            if ($gradeData['grade'] === $grade) {
                return $gradeData;
            }
        }

        return null;
    }

    /**
     * Check if grade exists in scale
     */
    public function hasGrade(string $grade): bool
    {
        return $this->getSalaryForGrade($grade) !== null;
    }

    /**
     * Get all grade codes
     */
    public function getGradeCodes(): array
    {
        return array_column($this->grades, 'grade');
    }

    /**
     * Validate salary against grade
     */
    public function validateSalaryForGrade(string $grade, float $salary): bool
    {
        $gradeData = $this->getSalaryForGrade($grade);

        if (! $gradeData) {
            return false;
        }

        return $salary >= $gradeData['min_salary'] && $salary <= $gradeData['max_salary'];
    }
}
