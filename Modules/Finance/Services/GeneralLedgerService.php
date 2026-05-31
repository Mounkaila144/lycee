<?php

namespace Modules\Finance\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Finance\Entities\Expense;
use Modules\Finance\Entities\Payment;

/**
 * Comptabilité générale consolidée (base trésorerie / cash).
 *
 * Résultat net = Recettes encaissées
 *   − Salaires employés décaissés (coût total employeur)
 *   − Salaires professeurs décaissés
 *   − Dépenses payées.
 */
class GeneralLedgerService
{
    /**
     * @return array<string, mixed>
     */
    public function resultat(string $from, string $to): array
    {
        $recettes = $this->recettes($from, $to);
        $salairesEmployes = $this->salairesEmployes($from, $to);
        $salairesProfs = $this->salairesProfesseurs($from, $to);
        $depenses = $this->depenses($from, $to);

        $totalSorties = $salairesEmployes + $salairesProfs + $depenses['total'];
        $resultatNet = $recettes['total'] - $totalSorties;

        return [
            'periode' => ['debut' => $from, 'fin' => $to],
            'recettes' => $recettes,
            'sorties' => [
                'salaires_employes' => round($salairesEmployes, 2),
                'salaires_professeurs' => round($salairesProfs, 2),
                'depenses' => $depenses,
                'total' => round($totalSorties, 2),
            ],
            'resultat_net' => round($resultatNet, 2),
            'est_benefice' => $resultatNet >= 0,
            'tendance_mensuelle' => $this->tendanceMensuelle($from, $to),
        ];
    }

    /**
     * @return array{total: float, par_methode: array<string, float>}
     */
    private function recettes(string $from, string $to): array
    {
        $total = (float) Payment::on('tenant')
            ->whereBetween('payment_date', [$from, $to])
            ->sum('amount');

        $parMethode = Payment::on('tenant')
            ->whereBetween('payment_date', [$from, $to])
            ->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method')
            ->map(fn ($v) => (float) $v);

        return ['total' => round($total, 2), 'par_methode' => $parMethode];
    }

    private function salairesEmployes(string $from, string $to): float
    {
        if (! Schema::connection('tenant')->hasTable('payroll_records')) {
            return 0.0;
        }

        $column = Schema::connection('tenant')->hasColumn('payroll_records', 'total_cost') ? 'total_cost' : 'net_salary';

        $query = DB::connection('tenant')->table('payroll_records')
            ->where('status', 'paid')
            ->whereBetween('payment_date', [$from, $to]);

        if (Schema::connection('tenant')->hasColumn('payroll_records', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return (float) $query->sum($column);
    }

    private function salairesProfesseurs(string $from, string $to): float
    {
        // Versements réels (gère les paiements partiels) — base trésorerie.
        if (Schema::connection('tenant')->hasTable('paiements_professeurs')) {
            return (float) DB::connection('tenant')->table('paiements_professeurs')
                ->whereBetween('date_paiement', [$from, $to])
                ->sum('montant');
        }

        if (Schema::connection('tenant')->hasTable('paies_professeurs')) {
            return (float) DB::connection('tenant')->table('paies_professeurs')
                ->whereBetween('date_paiement', [$from, $to])
                ->sum('montant_paye');
        }

        return 0.0;
    }

    /**
     * @return array{total: float, par_categorie: array<int, array<string, mixed>>}
     */
    private function depenses(string $from, string $to): array
    {
        $rows = Expense::on('tenant')
            ->paidBetween($from, $to)
            ->selectRaw('expense_category_id, SUM(amount) as total')
            ->groupBy('expense_category_id')
            ->with('category')
            ->get();

        $parCategorie = $rows->map(fn ($r) => [
            'categorie' => $r->category?->name ?? '—',
            'icon' => $r->category?->icon,
            'total' => (float) $r->total,
        ])->values();

        return ['total' => round((float) $rows->sum('total'), 2), 'par_categorie' => $parCategorie];
    }

    /**
     * Recettes vs sorties par mois sur la période (max 24 mois).
     *
     * @return array<int, array<string, mixed>>
     */
    private function tendanceMensuelle(string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to)->startOfMonth();
        $trend = [];
        $guard = 0;

        while ($start->lte($end) && $guard < 24) {
            $mFrom = $start->copy()->startOfMonth()->toDateString();
            $mTo = $start->copy()->endOfMonth()->toDateString();

            $recettes = (float) Payment::on('tenant')->whereBetween('payment_date', [$mFrom, $mTo])->sum('amount');
            $sorties = $this->salairesEmployes($mFrom, $mTo)
                + $this->salairesProfesseurs($mFrom, $mTo)
                + (float) Expense::on('tenant')->paidBetween($mFrom, $mTo)->sum('amount');

            $trend[] = [
                'mois' => $start->format('Y-m'),
                'recettes' => round($recettes, 2),
                'sorties' => round($sorties, 2),
                'resultat' => round($recettes - $sorties, 2),
            ];

            $start->addMonth();
            $guard++;
        }

        return $trend;
    }
}
