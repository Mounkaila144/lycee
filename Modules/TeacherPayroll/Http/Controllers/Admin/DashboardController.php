<?php

namespace Modules\TeacherPayroll\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\TeacherPayroll\Entities\PaieProfesseur;
use Modules\TeacherPayroll\Entities\PeriodePaie;
use Modules\TeacherPayroll\Entities\Professeur;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $professeurs = Professeur::on('tenant')->count();
        $actifs = Professeur::on('tenant')->where('actif', true)->count();

        $parStatut = Professeur::on('tenant')
            ->selectRaw('statut_professeur, COUNT(*) as total')
            ->groupBy('statut_professeur')
            ->pluck('total', 'statut_professeur');

        $dernierePeriode = PeriodePaie::on('tenant')->orderBy('annee', 'desc')->orderBy('mois', 'desc')->first();

        $totalNetDerniere = $dernierePeriode
            ? (float) PaieProfesseur::on('tenant')->where('periode_paie_id', $dernierePeriode->id)->sum('net_a_payer')
            : 0;

        return response()->json([
            'data' => [
                'total_professeurs' => $professeurs,
                'professeurs_actifs' => $actifs,
                'par_statut' => $parStatut,
                'derniere_periode' => $dernierePeriode,
                'total_net_derniere_periode' => $totalNetDerniere,
            ],
        ]);
    }
}
