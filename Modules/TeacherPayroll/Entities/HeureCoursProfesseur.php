<?php

namespace Modules\TeacherPayroll\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Subject;

class HeureCoursProfesseur extends Model
{
    protected $connection = 'tenant';

    protected $table = 'heures_cours_professeurs';

    public const STATUTS = ['PREVU', 'EFFECTUE', 'ABSENT', 'ANNULE', 'REMPLACE', 'VALIDE', 'REJETE'];

    protected $fillable = [
        'professeur_id',
        'classe_id',
        'matiere_id',
        'annee_scolaire_id',
        'date_cours',
        'heure_debut',
        'heure_fin',
        'nombre_heures',
        'statut',
        'remplace_par_professeur_id',
        'motif',
        'valide_par',
        'date_validation',
    ];

    protected function casts(): array
    {
        return [
            'date_cours' => 'date',
            'nombre_heures' => 'decimal:2',
            'date_validation' => 'datetime',
        ];
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public function professeur(): BelongsTo
    {
        return $this->belongsTo(Professeur::class, 'professeur_id');
    }

    public function remplacant(): BelongsTo
    {
        return $this->belongsTo(Professeur::class, 'remplace_par_professeur_id');
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'matiere_id');
    }

    public function scopeValide(Builder $query): Builder
    {
        return $query->where('statut', 'VALIDE');
    }

    public function isValide(): bool
    {
        return $this->statut === 'VALIDE';
    }
}
