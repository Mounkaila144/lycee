<?php

namespace Modules\TeacherPayroll\Services;

use Illuminate\Http\Request;
use Modules\TeacherPayroll\Entities\HistoriqueActionPaie;

/**
 * Journalisation des actions sensibles de la paie professeur.
 */
class PayrollAuditLogger
{
    public static function log(
        string $action,
        ?string $entite = null,
        ?int $entiteId = null,
        ?array $ancienneValeur = null,
        ?array $nouvelleValeur = null
    ): void {
        $request = request();

        HistoriqueActionPaie::on('tenant')->create([
            'user_id' => auth('tenant')->id(),
            'action' => $action,
            'entite' => $entite,
            'entite_id' => $entiteId,
            'ancienne_valeur' => $ancienneValeur,
            'nouvelle_valeur' => $nouvelleValeur,
            'date_action' => now(),
            'adresse_ip' => $request instanceof Request ? $request->ip() : null,
        ]);
    }
}
