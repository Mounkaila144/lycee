<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Database\Factories\ProgrammeFactory;
use Modules\UsersGuard\Entities\User;

class Programme extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'programmes';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ProgrammeFactory::new();
    }

    protected $fillable = [
        'code',
        'libelle',
        'type',
        'duree_annees',
        'description',
        'responsable_id',
        'statut',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'duree_annees' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /**
     * Relation vers les niveaux du programme (ProgramLevel)
     */
    public function levels(): HasMany
    {
        return $this->hasMany(ProgramLevel::class, 'program_id');
    }

    /**
     * Alias pour la relation levels() - évite le conflit avec l'accessor
     */
    public function programLevels(): HasMany
    {
        return $this->hasMany(ProgramLevel::class, 'program_id');
    }

    public function creditConfigurations(): HasMany
    {
        return $this->hasMany(LevelCreditConfiguration::class, 'program_id');
    }

    /**
     * Relation vers l'historique des modifications
     */
    public function history(): HasMany
    {
        return $this->hasMany(ProgrammeHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * Relation vers les modules du programme
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'module_programs');
    }

    /**
     * Vérifier si le programme a un niveau spécifique
     */
    public function hasLevel(string $level): bool
    {
        return $this->programLevels()->where('level', $level)->exists();
    }

    /**
     * Obtenir les niveaux sous forme de tableau de strings
     */
    public function getLevelNamesAttribute(): array
    {
        return $this->programLevels()->pluck('level')->toArray();
    }

    /**
     * Scopes
     */
    public function scopeActif($query)
    {
        return $query->where('statut', 'Actif');
    }

    public function scopeBrouillon($query)
    {
        return $query->where('statut', 'Brouillon');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Méthodes métier
     */
    public function canBeModified(): bool
    {
        return in_array($this->statut, ['Brouillon', 'Inactif']);
    }

    public function canBeDeleted(): bool
    {
        // TODO: Vérifier s'il y a des inscriptions actives
        return $this->statut !== 'Actif';
    }

    public function canBeActivated(): bool
    {
        // Programme doit être complet pour être activé
        return $this->statut === 'Brouillon'
            && ! empty($this->code)
            && ! empty($this->libelle)
            && ! empty($this->type)
            && $this->duree_annees > 0
            && $this->programLevels()->count() > 0; // Au moins 1 niveau associé
    }

    /**
     * Transitions de statut validées
     */
    public function transitionTo(string $newStatut): bool
    {
        $validTransitions = [
            'Brouillon' => ['Actif'],
            'Actif' => ['Inactif'],
            'Inactif' => ['Actif', 'Archivé'],
            'Archivé' => [],
        ];

        $currentStatut = $this->statut;

        if (! isset($validTransitions[$currentStatut])) {
            return false;
        }

        if (! in_array($newStatut, $validTransitions[$currentStatut])) {
            return false;
        }

        // Validation spécifique pour activation
        if ($newStatut === 'Actif' && ! $this->canBeActivated()) {
            return false;
        }

        $this->statut = $newStatut;

        return $this->save();
    }
}
