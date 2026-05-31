<?php

namespace Modules\TeacherPayroll\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceProfesseur extends Model
{
    protected $connection = 'tenant';

    protected $table = 'absences_professeurs';

    protected $fillable = [
        'professeur_id',
        'date_absence',
        'heure_debut',
        'heure_fin',
        'nombre_heures',
        'motif',
        'justifiee',
        'impact_sur_paie',
        'montant_retenue',
    ];

    protected function casts(): array
    {
        return [
            'date_absence' => 'date',
            'nombre_heures' => 'decimal:2',
            'justifiee' => 'boolean',
            'impact_sur_paie' => 'boolean',
            'montant_retenue' => 'decimal:2',
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
}
