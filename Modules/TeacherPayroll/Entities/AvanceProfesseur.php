<?php

namespace Modules\TeacherPayroll\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvanceProfesseur extends Model
{
    protected $connection = 'tenant';

    protected $table = 'avances_professeurs';

    public const STATUTS = ['EN_ATTENTE', 'APPROUVEE', 'DEDUITE', 'ANNULEE'];

    protected $fillable = [
        'professeur_id',
        'montant',
        'date_avance',
        'motif',
        'statut',
        'periode_deduction_id',
        'montant_deduit',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'montant_deduit' => 'decimal:2',
            'date_avance' => 'date',
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

    public function scopeApprouvee(Builder $query): Builder
    {
        return $query->where('statut', 'APPROUVEE');
    }

    /**
     * Montant restant à déduire sur cette avance.
     */
    public function getResteADeduireAttribute(): float
    {
        return max(0, (float) $this->montant - (float) $this->montant_deduit);
    }
}
