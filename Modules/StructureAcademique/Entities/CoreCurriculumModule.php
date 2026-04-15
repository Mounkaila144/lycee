<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreCurriculumModule extends Model
{
    protected $connection = 'tenant';

    protected $table = 'core_curriculum_modules';

    protected $fillable = [
        'programme_id',
        'level',
        'module_id',
    ];

    /**
     * Relations
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Scopes
     */
    public function scopeForProgramme(Builder $query, int $programmeId): Builder
    {
        return $query->where('programme_id', $programmeId);
    }

    public function scopeForLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }

    /**
     * Obtenir tous les modules du tronc commun pour un programme et niveau
     */
    public static function getModulesForProgrammeLevel(int $programmeId, string $level)
    {
        return self::with('module')
            ->where('programme_id', $programmeId)
            ->where('level', $level)
            ->get()
            ->pluck('module');
    }
}
