<?php

namespace Modules\TeacherPayroll\Entities;

use Illuminate\Database\Eloquent\Model;

class HistoriqueActionPaie extends Model
{
    protected $connection = 'tenant';

    protected $table = 'historique_actions_paie';

    protected $fillable = [
        'user_id',
        'action',
        'entite',
        'entite_id',
        'ancienne_valeur',
        'nouvelle_valeur',
        'date_action',
        'adresse_ip',
    ];

    protected function casts(): array
    {
        return [
            'ancienne_valeur' => 'array',
            'nouvelle_valeur' => 'array',
            'date_action' => 'datetime',
        ];
    }
}
