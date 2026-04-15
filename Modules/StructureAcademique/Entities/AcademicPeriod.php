<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicPeriod extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'academic_periods';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'semester_id',
        'name',
        'type',
        'start_date',
        'end_date',
        'description',
    ];

    /**
     * Casts
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Relations
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Scopes
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeHolidays(Builder $query): Builder
    {
        return $query->whereIn('type', ['Jour férié', 'Vacances']);
    }

    public function scopeExams(Builder $query): Builder
    {
        return $query->where('type', 'Session examens');
    }

    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_date', '>', now());
    }

    /**
     * Vérifier si la période est dans les limites du semestre
     */
    public function isWithinSemester(): bool
    {
        if (! $this->semester) {
            return false;
        }

        return $this->start_date->gte($this->semester->start_date) &&
               $this->end_date->lte($this->semester->end_date);
    }
}
