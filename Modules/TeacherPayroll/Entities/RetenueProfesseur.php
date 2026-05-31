<?php

namespace Modules\TeacherPayroll\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetenueProfesseur extends Model
{
    protected $connection = 'tenant';

    protected $table = 'retenues_professeurs';

    protected $fillable = [
        'professeur_id',
        'periode_paie_id',
        'type_retenue',
        'libelle',
        'montant',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
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

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodePaie::class, 'periode_paie_id');
    }
}
