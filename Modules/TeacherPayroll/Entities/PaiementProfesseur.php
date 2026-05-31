<?php

namespace Modules\TeacherPayroll\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiementProfesseur extends Model
{
    protected $connection = 'tenant';

    protected $table = 'paiements_professeurs';

    protected $fillable = [
        'paie_professeur_id',
        'montant',
        'date_paiement',
        'mode_reglement',
        'reference',
        'note',
        'enregistre_par',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_paiement' => 'date',
        ];
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public function paie(): BelongsTo
    {
        return $this->belongsTo(PaieProfesseur::class, 'paie_professeur_id');
    }
}
