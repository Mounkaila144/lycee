<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModuleEvaluationConfig extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'module_evaluation_configs';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'module_id',
        'semester_id',
        'name',
        'type',
        'coefficient',
        'max_score',
        'planned_date',
        'is_eliminatory',
        'elimination_threshold',
        'order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'coefficient' => 'decimal:2',
            'max_score' => 'decimal:2',
            'elimination_threshold' => 'decimal:2',
            'is_eliminatory' => 'boolean',
            'planned_date' => 'date',
            'order' => 'integer',
        ];
    }

    /**
     * Relation vers Module
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Relation vers Semester
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Scope pour filtrer par module et semestre
     */
    public function scopeForModuleAndSemester($query, int $moduleId, int $semesterId)
    {
        return $query->where('module_id', $moduleId)
            ->where('semester_id', $semesterId);
    }

    /**
     * Scope pour filtrer par statut
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope pour les évaluations publiées
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'Published');
    }

    /**
     * Scope pour filtrer par type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check si cette évaluation peut être modifiée
     */
    public function canBeModified(): bool
    {
        // Ne peut pas être modifiée si elle est publiée
        // TODO: Ajouter vérification si des notes ont été saisies
        return $this->status === 'Draft';
    }

    /**
     * Publier la configuration
     */
    public function publish(): void
    {
        $this->update(['status' => 'Published']);
    }
}
