<?php

namespace Modules\TeacherPayroll\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodePaie extends Model
{
    protected $connection = 'tenant';

    protected $table = 'periodes_paie';

    public const STATUTS = ['OUVERTE', 'EN_CALCUL', 'VALIDEE', 'PAYEE', 'CLOTUREE'];

    protected $fillable = [
        'annee_scolaire_id',
        'mois',
        'annee',
        'date_debut',
        'date_fin',
        'statut',
        'est_vacances',
    ];

    protected function casts(): array
    {
        return [
            'mois' => 'integer',
            'annee' => 'integer',
            'date_debut' => 'date',
            'date_fin' => 'date',
            'est_vacances' => 'boolean',
        ];
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public function paies(): HasMany
    {
        return $this->hasMany(PaieProfesseur::class, 'periode_paie_id');
    }

    public function isModifiable(): bool
    {
        return ! in_array($this->statut, ['CLOTUREE'], true);
    }

    public function getLibelleAttribute(): string
    {
        $mois = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
        ];

        return ($mois[$this->mois] ?? $this->mois).' '.$this->annee;
    }
}
