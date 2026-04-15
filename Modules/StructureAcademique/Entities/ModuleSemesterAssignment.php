<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModuleSemesterAssignment extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'module_semester_assignments';

    protected $fillable = [
        'module_id',
        'semester_id',
        'programme_id',
        'is_active',
    ];

    /**
     * Casts
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relations
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    /**
     * Scopes
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeBySemester(Builder $query, int $semesterId): Builder
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeByProgramme(Builder $query, int $programmeId): Builder
    {
        return $query->where('programme_id', $programmeId);
    }

    public function scopeByModule(Builder $query, int $moduleId): Builder
    {
        return $query->where('module_id', $moduleId);
    }
}
