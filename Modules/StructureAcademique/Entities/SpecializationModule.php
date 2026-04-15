<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecializationModule extends Model
{
    protected $connection = 'tenant';

    protected $table = 'specialization_modules';

    protected $fillable = [
        'specialization_id',
        'module_id',
        'type',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    /**
     * Relations
     */
    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Scopes
     */
    public function scopeMandatory(Builder $query): Builder
    {
        return $query->where('type', 'Obligatoire');
    }

    public function scopeOptional(Builder $query): Builder
    {
        return $query->where('type', 'Optionnel');
    }

    public function scopeForSpecialization(Builder $query, int $specializationId): Builder
    {
        return $query->where('specialization_id', $specializationId);
    }

    /**
     * Vérifier si le module est obligatoire
     */
    public function isMandatory(): bool
    {
        return $this->type === 'Obligatoire';
    }

    /**
     * Vérifier si le module est optionnel
     */
    public function isOptional(): bool
    {
        return $this->type === 'Optionnel';
    }

    /**
     * Obtenir le nombre de places restantes (pour modules optionnels)
     */
    public function getRemainingCapacityAttribute(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        $enrolledCount = StudentModuleChoice::where('module_id', $this->module_id)
            ->where('specialization_id', $this->specialization_id)
            ->where('status', 'Confirmé')
            ->count();

        return max(0, $this->capacity - $enrolledCount);
    }
}
