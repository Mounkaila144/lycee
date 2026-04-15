<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Database\Factories\ModuleGroupFactory;

class ModuleGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'module_groups';

    protected $fillable = [
        'code',
        'name',
        'semester_id',
        'programme_id',
        'level',
        'description',
    ];

    /**
     * Resolve route binding for tenant connection
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ModuleGroupFactory
    {
        return ModuleGroupFactory::new();
    }

    protected function casts(): array
    {
        return [
            'semester_id' => 'integer',
            'programme_id' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    // Relations

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class, 'module_group_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    // Computed attributes

    public function getTotalEctsAttribute(): int
    {
        return $this->modules->sum('credits_ects');
    }

    public function getTotalCoefficientAttribute(): float
    {
        return (float) $this->modules->sum('coefficient');
    }

    public function getModulesCountAttribute(): int
    {
        return $this->modules->count();
    }

    // Scopes

    public function scopeForProgramme($query, int $programmeId)
    {
        return $query->where('programme_id', $programmeId);
    }

    public function scopeForSemester($query, int $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeForLevel($query, string $level)
    {
        return $query->where('level', $level);
    }
}
