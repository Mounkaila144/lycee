<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\StructureAcademique\Database\Factories\EliminatoryModuleFactory;

class EliminatoryModule extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return EliminatoryModuleFactory::new();
    }

    protected $connection = 'tenant';

    protected $table = 'eliminatory_modules';

    protected $fillable = [
        'programme_id',
        'module_id',
        'level',
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
        return $this->belongsTo(Module::class, 'module_id');
    }

    /**
     * Scopes
     */
    public function scopeForProgramme($query, int $programmeId)
    {
        return $query->where('programme_id', $programmeId);
    }

    public function scopeForLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Get all eliminatory modules for a programme and level
     */
    public static function getForProgrammeAndLevel(int $programmeId, string $level)
    {
        return self::with('module')
            ->where('programme_id', $programmeId)
            ->where('level', $level)
            ->get();
    }

    /**
     * Check if a module is eliminatory for given programme and level
     */
    public static function isEliminatory(int $programmeId, int $moduleId, string $level): bool
    {
        return self::where('programme_id', $programmeId)
            ->where('module_id', $moduleId)
            ->where('level', $level)
            ->exists();
    }
}
