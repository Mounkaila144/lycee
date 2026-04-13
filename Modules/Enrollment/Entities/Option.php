<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Database\Factories\OptionFactory;
use Modules\StructureAcademique\Entities\Programme;

class Option extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'options';

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): OptionFactory
    {
        return OptionFactory::new();
    }

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'programme_id',
        'level',
        'code',
        'name',
        'description',
        'capacity',
        'prerequisites',
        'is_mandatory',
        'choice_start_date',
        'choice_end_date',
        'status',
    ];

    /**
     * Casts
     */
    protected function casts(): array
    {
        return [
            'prerequisites' => 'array',
            'is_mandatory' => 'boolean',
            'capacity' => 'integer',
            'choice_start_date' => 'date',
            'choice_end_date' => 'date',
        ];
    }

    /**
     * Relations
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_id');
    }

    public function choices(): HasMany
    {
        return $this->hasMany(OptionChoice::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OptionAssignment::class);
    }

    /**
     * Scopes
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'Open');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'Closed');
    }

    public function scopeForProgramme(Builder $query, int $programmeId): Builder
    {
        return $query->where('programme_id', $programmeId);
    }

    public function scopeForLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }

    public function scopeChoicePeriodActive(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('choice_start_date', '<=', $today)
            ->where('choice_end_date', '>=', $today);
    }

    public function scopeMandatory(Builder $query): Builder
    {
        return $query->where('is_mandatory', true);
    }

    public function scopeOptional(Builder $query): Builder
    {
        return $query->where('is_mandatory', false);
    }

    /**
     * Business Logic Methods
     */
    public function isChoicePeriodOpen(): bool
    {
        $today = now()->startOfDay();

        return $this->status === 'Open'
            && $today->greaterThanOrEqualTo($this->choice_start_date)
            && $today->lessThanOrEqualTo($this->choice_end_date);
    }

    public function getRemainingCapacity(?int $academicYearId = null): int
    {
        $query = $this->assignments();

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        $currentCount = $query->count();

        return max(0, $this->capacity - $currentCount);
    }

    public function isFull(?int $academicYearId = null): bool
    {
        return $this->getRemainingCapacity($academicYearId) === 0;
    }

    public function hasPrerequisites(): bool
    {
        return ! empty($this->prerequisites);
    }

    public function getPrerequisitesList(): array
    {
        if (empty($this->prerequisites)) {
            return [];
        }

        return $this->prerequisites;
    }
}
