<?php

namespace Modules\TeacherPayroll\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaieProfesseur extends Model
{
    protected $connection = 'tenant';

    protected $table = 'paies_professeurs';

    public const STATUTS = ['BROUILLON', 'CALCULEE', 'VALIDEE', 'PARTIELLEMENT_PAYEE', 'PAYEE', 'ANNULEE'];

    protected $fillable = [
        'professeur_id',
        'contrat_professeur_id',
        'periode_paie_id',
        'mode_paiement',
        'salaire_base',
        'taux_horaire',
        'taux_heure_supplementaire',
        'nombre_heures_validees',
        'nombre_heures_supplementaires',
        'montant_heures',
        'montant_heures_supplementaires',
        'montant_forfait',
        'total_primes',
        'total_retenues',
        'total_avances',
        'montant_brut',
        'net_a_payer',
        'solde_reporte',
        'montant_paye',
        'statut',
        'date_calcul',
        'date_validation',
        'date_paiement',
        'mode_reglement',
        'reference_paiement',
        'details_primes',
        'details_retenues',
        'calcule_par',
        'valide_par',
    ];

    protected function casts(): array
    {
        return [
            'salaire_base' => 'decimal:2',
            'taux_horaire' => 'decimal:2',
            'taux_heure_supplementaire' => 'decimal:2',
            'nombre_heures_validees' => 'decimal:2',
            'nombre_heures_supplementaires' => 'decimal:2',
            'montant_heures' => 'decimal:2',
            'montant_heures_supplementaires' => 'decimal:2',
            'montant_forfait' => 'decimal:2',
            'total_primes' => 'decimal:2',
            'total_retenues' => 'decimal:2',
            'total_avances' => 'decimal:2',
            'montant_brut' => 'decimal:2',
            'net_a_payer' => 'decimal:2',
            'solde_reporte' => 'decimal:2',
            'montant_paye' => 'decimal:2',
            'date_calcul' => 'datetime',
            'date_validation' => 'datetime',
            'date_paiement' => 'datetime',
            'details_primes' => 'array',
            'details_retenues' => 'array',
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

    public function contrat(): BelongsTo
    {
        return $this->belongsTo(ContratProfesseur::class, 'contrat_professeur_id');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodePaie::class, 'periode_paie_id');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(PaiementProfesseur::class, 'paie_professeur_id')->orderBy('date_paiement', 'desc');
    }

    public function scopeValidee(Builder $query): Builder
    {
        return $query->where('statut', 'VALIDEE');
    }

    public function isModifiable(): bool
    {
        return in_array($this->statut, ['BROUILLON', 'CALCULEE'], true);
    }

    public function getResteAPayerAttribute(): float
    {
        return max(0, (float) $this->net_a_payer - (float) $this->montant_paye);
    }
}
