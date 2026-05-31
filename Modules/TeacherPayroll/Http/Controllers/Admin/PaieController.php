<?php

namespace Modules\TeacherPayroll\Http\Controllers\Admin;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\TeacherPayroll\Entities\PaieProfesseur;
use Modules\TeacherPayroll\Entities\PeriodePaie;
use Modules\TeacherPayroll\Entities\Professeur;
use Modules\TeacherPayroll\Http\Requests\EnregistrerPaiementRequest;
use Modules\TeacherPayroll\Services\PayrollAuditLogger;
use Modules\TeacherPayroll\Services\TeacherPayrollService;

class PaieController extends Controller
{
    public function __construct(private TeacherPayrollService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = PaieProfesseur::on('tenant')->with(['professeur', 'periode', 'paiements']);

        if ($periodeId = $request->input('periode_paie_id')) {
            $query->where('periode_paie_id', $periodeId);
        }
        if ($professeurId = $request->input('professeur_id')) {
            $query->where('professeur_id', $professeurId);
        }
        if ($statut = $request->input('statut')) {
            $query->where('statut', $statut);
        }

        return response()->json(['data' => $query->orderBy('created_at', 'desc')->get()]);
    }

    public function show(int $id): JsonResponse
    {
        $paie = PaieProfesseur::on('tenant')
            ->with(['professeur', 'periode', 'contrat', 'paiements'])
            ->findOrFail($id);

        return response()->json(['data' => $paie]);
    }

    /**
     * Recalcule une paie individuelle (seulement si modifiable).
     */
    public function recalculer(int $id): JsonResponse
    {
        $paie = PaieProfesseur::on('tenant')->findOrFail($id);

        if (! $paie->isModifiable()) {
            return response()->json(['message' => 'Paie déjà validée ou payée, recalcul impossible'], 422);
        }

        $professeur = Professeur::on('tenant')->findOrFail($paie->professeur_id);
        $periode = PeriodePaie::on('tenant')->findOrFail($paie->periode_paie_id);

        $paie = $this->service->genererPaie($professeur, $periode, true);

        return response()->json(['message' => 'Paie recalculée', 'data' => $paie]);
    }

    public function valider(int $id): JsonResponse
    {
        $paie = PaieProfesseur::on('tenant')->findOrFail($id);
        $paie = $this->service->validerPaie($paie);

        return response()->json(['message' => 'Paie validée', 'data' => $paie]);
    }

    public function annuler(int $id): JsonResponse
    {
        $paie = PaieProfesseur::on('tenant')->findOrFail($id);

        if ($paie->montant_paye > 0) {
            return response()->json(['message' => 'Une paie déjà réglée ne peut pas être annulée'], 422);
        }

        $paie->update(['statut' => 'ANNULEE']);
        PayrollAuditLogger::log('ANNULATION_PAIE', 'paie_professeur', $paie->id);

        return response()->json(['message' => 'Paie annulée', 'data' => $paie]);
    }

    /**
     * Enregistre un règlement (total ou partiel) sur une paie validée.
     */
    public function enregistrerPaiement(EnregistrerPaiementRequest $request, int $id): JsonResponse
    {
        $paie = PaieProfesseur::on('tenant')->findOrFail($id);

        if (! in_array($paie->statut, ['VALIDEE', 'PARTIELLEMENT_PAYEE'], true)) {
            return response()->json(['message' => 'La paie doit être validée avant tout paiement'], 422);
        }

        $montant = (float) $request->validated()['montant'];
        if ($montant > $paie->reste_a_payer + 0.01) {
            return response()->json(['message' => 'Le montant dépasse le reste à payer ('.$paie->reste_a_payer.')'], 422);
        }

        $paie = $this->service->enregistrerPaiement($paie, $montant, $request->validated());

        return response()->json(['message' => 'Paiement enregistré', 'data' => $paie]);
    }

    /**
     * Fiche de paie au format PDF (inline).
     */
    public function fichePaie(int $id): Response
    {
        $paie = PaieProfesseur::on('tenant')
            ->with(['professeur', 'periode', 'contrat'])
            ->findOrFail($id);

        $affectations = \Modules\TeacherPayroll\Entities\AffectationProfesseur::on('tenant')
            ->with(['classe', 'matiere'])
            ->where('professeur_id', $paie->professeur_id)
            ->where('actif', true)
            ->get();

        $pdf = Pdf::loadHTML($this->renderHtml($paie, $affectations));

        return $pdf->stream('fiche-paie-'.$paie->id.'.pdf');
    }

    private function renderHtml(PaieProfesseur $paie, $affectations): string
    {
        $header = \Modules\Settings\Entities\TenantSetting::schoolHeaderHtml();
        $prof = $paie->professeur;
        $periode = $paie->periode;

        $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $nom = e($prof?->nom_complet ?? '');
        $statut = e($prof?->statut_professeur ?? '');
        $mode = e($paie->mode_paiement);
        $libellePeriode = e($periode?->libelle ?? '');

        $matieres = $affectations->map(fn ($a) => e($a->matiere?->name ?? ''))->filter()->unique()->implode(', ');
        $classes = $affectations->map(fn ($a) => e($a->classe?->name ?? ''))->filter()->unique()->implode(', ');

        $lignesPrimes = '';
        foreach ((array) $paie->details_primes as $p) {
            $lignesPrimes .= '<tr><td>'.e($p['libelle'] ?? '').'</td><td class="r">'.$fmt($p['montant'] ?? 0).'</td></tr>';
        }
        if ($lignesPrimes === '') {
            $lignesPrimes = '<tr><td colspan="2" class="muted">Aucune prime</td></tr>';
        }

        $lignesRetenues = '';
        foreach ((array) $paie->details_retenues as $r) {
            $lignesRetenues .= '<tr><td>'.e($r['libelle'] ?? '').'</td><td class="r">'.$fmt($r['montant'] ?? 0).'</td></tr>';
        }
        if ($lignesRetenues === '') {
            $lignesRetenues = '<tr><td colspan="2" class="muted">Aucune retenue</td></tr>';
        }

        // Lignes de rémunération selon le mode.
        $lignesRemu = '';
        if (in_array($mode, ['MENSUEL', 'HYBRIDE'], true)) {
            $lignesRemu .= '<tr><td>Salaire de base</td><td class="r">'.$fmt($paie->salaire_base).'</td></tr>';
        }
        if ($mode === 'HORAIRE') {
            $lignesRemu .= '<tr><td>Heures validées ('.$fmt($paie->nombre_heures_validees).' h × '.$fmt($paie->taux_horaire).')</td><td class="r">'.$fmt($paie->montant_heures).'</td></tr>';
        }
        if ($mode === 'HYBRIDE') {
            $lignesRemu .= '<tr><td>Heures supplémentaires ('.$fmt($paie->nombre_heures_supplementaires).' h × '.$fmt($paie->taux_heure_supplementaire).')</td><td class="r">'.$fmt($paie->montant_heures_supplementaires).'</td></tr>';
        }
        if ($mode === 'FORFAIT') {
            $lignesRemu .= '<tr><td>Forfait</td><td class="r">'.$fmt($paie->montant_forfait).'</td></tr>';
        }

        $brut = $fmt($paie->montant_brut);
        $totalPrimes = $fmt($paie->total_primes);
        $totalRetenues = $fmt($paie->total_retenues);
        $totalAvances = $fmt($paie->total_avances);
        $net = $fmt($paie->net_a_payer);
        $solde = $fmt($paie->solde_reporte);
        $datePaiement = e($paie->date_paiement?->format('d/m/Y') ?? '—');
        $reglement = e($paie->mode_reglement ?? '—');

        $soldeLigne = (float) $paie->solde_reporte > 0
            ? '<div class="row"><span class="label">Solde reporté :</span> '.$solde.' FCFA</div>'
            : '';

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;color:#1a1a1a;padding:36px;font-size:12px;}
h1{text-align:center;font-size:18px;margin:14px 0 6px;}
.sub{text-align:center;color:#555;margin-bottom:18px;}
.row{margin:4px 0;}
.label{font-weight:bold;display:inline-block;width:150px;}
table{width:100%;border-collapse:collapse;margin:8px 0 14px;}
th,td{border:1px solid #ccc;padding:6px 8px;font-size:12px;}
th{background:#f0f4f8;text-align:left;}
.r{text-align:right;}
.muted{color:#888;font-style:italic;}
.net{font-size:16px;font-weight:bold;color:#1565c0;}
.cols{width:100%;}
.cols td{border:none;vertical-align:top;padding:0 6px;}
.sign{margin-top:46px;}
.sign td{border:none;text-align:center;padding-top:34px;border-top:1px solid #333;}
.box{border:1px solid #ccc;border-radius:4px;padding:10px 14px;margin-top:10px;}
</style></head><body>
{$header}
<h1>Fiche de paie — Professeur</h1>
<div class="sub">Période : {$libellePeriode}</div>

<table class="cols"><tr>
<td>
  <div class="row"><span class="label">Professeur :</span> {$nom}</div>
  <div class="row"><span class="label">Statut :</span> {$statut}</div>
  <div class="row"><span class="label">Mode de paiement :</span> {$mode}</div>
</td>
<td>
  <div class="row"><span class="label">Matières :</span> {$matieres}</div>
  <div class="row"><span class="label">Classes :</span> {$classes}</div>
</td>
</tr></table>

<table>
<tr><th>Rémunération</th><th class="r">Montant (FCFA)</th></tr>
{$lignesRemu}
</table>

<table class="cols"><tr>
<td>
  <table>
  <tr><th>Primes</th><th class="r">FCFA</th></tr>
  {$lignesPrimes}
  <tr><td><b>Total primes</b></td><td class="r"><b>{$totalPrimes}</b></td></tr>
  </table>
</td>
<td>
  <table>
  <tr><th>Retenues</th><th class="r">FCFA</th></tr>
  {$lignesRetenues}
  <tr><td><b>Total retenues</b></td><td class="r"><b>{$totalRetenues}</b></td></tr>
  </table>
</td>
</tr></table>

<div class="box">
  <div class="row"><span class="label">Montant brut :</span> {$brut} FCFA</div>
  <div class="row"><span class="label">Total retenues :</span> {$totalRetenues} FCFA</div>
  <div class="row"><span class="label">Total avances :</span> {$totalAvances} FCFA</div>
  <div class="row net"><span class="label">NET À PAYER :</span> {$net} FCFA</div>
  {$soldeLigne}
</div>

<div class="row" style="margin-top:14px;"><span class="label">Mode de règlement :</span> {$reglement}</div>
<div class="row"><span class="label">Date de paiement :</span> {$datePaiement}</div>

<table class="sign"><tr>
<td>Signature administration</td>
<td>Signature professeur</td>
</tr></table>
</body></html>
HTML;
    }
}
