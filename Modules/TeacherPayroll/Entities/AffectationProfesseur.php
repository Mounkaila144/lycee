<?php

namespace Modules\TeacherPayroll\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Subject;

class AffectationProfesseur extends Model
{
    protected $connection = 'tenant';

    protected $table = 'affectations_professeurs';

    protected $fillable = [
        'professeur_id',
        'classe_id',
        'matiere_id',
        'annee_scolaire_id',
        'volume_horaire_hebdomadaire',
        'coefficient',
        'date_debut',
        'date_fin',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'volume_horaire_hebdomadaire' => 'decimal:2',
            'coefficient' => 'decimal:2',
            'date_debut' => 'date',
            'date_fin' => 'date',
            'actif' => 'boolean',
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

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'matiere_id');
    }

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('actif', true);
    }
}
