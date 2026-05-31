<?php

namespace Modules\TeacherPayroll\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContratProfesseur extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'contrats_professeurs';

    public const MODES_PAIEMENT = ['MENSUEL', 'HORAIRE', 'HYBRIDE', 'FORFAIT'];

    public const STATUTS = ['ACTIF', 'SUSPENDU', 'TERMINE'];

    protected $fillable = [
        'professeur_id',
        'annee_scolaire_id',
        'date_debut',
        'date_fin',
        'type_contrat',
        'mode_paiement',
        'salaire_base',
        'taux_horaire',
        'taux_heure_supplementaire',
        'montant_forfait',
        'volume_horaire_mensuel_prevu',
        'volume_horaire_hebdomadaire_prevu',
        'paiement_pendant_vacances',
        'devise',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'salaire_base' => 'decimal:2',
            'taux_horaire' => 'decimal:2',
            'taux_heure_supplementaire' => 'decimal:2',
            'montant_forfait' => 'decimal:2',
            'volume_horaire_mensuel_prevu' => 'decimal:2',
            'volume_horaire_hebdomadaire_prevu' => 'decimal:2',
            'paiement_pendant_vacances' => 'boolean',
            'deleted_at' => 'datetime',
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

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('statut', 'ACTIF');
    }

    public function isActif(): bool
    {
        return $this->statut === 'ACTIF';
    }
}
