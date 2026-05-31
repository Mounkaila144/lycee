<?php

namespace Modules\TeacherPayroll\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TeacherPayroll\Entities\PeriodePaie;
use Modules\TeacherPayroll\Http\Requests\StorePeriodeRequest;
use Modules\TeacherPayroll\Services\PayrollAuditLogger;
use Modules\TeacherPayroll\Services\TeacherPayrollService;

class PeriodePaieController extends Controller
{
    public function __construct(private TeacherPayrollService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = PeriodePaie::on('tenant')->withCount('paies');

        if ($annee = $request->input('annee')) {
            $query->where('annee', $annee);
        }

        return response()->json(['data' => $query->orderBy('annee', 'desc')->orderBy('mois', 'desc')->get()]);
    }

    public function store(StorePeriodeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['statut'] = 'OUVERTE';
        $data['est_vacances'] = $data['est_vacances'] ?? false;

        $periode = PeriodePaie::on('tenant')->create($data);

        return response()->json(['message' => 'Période créée', 'data' => $periode], 201);
    }

    public function show(int $id): JsonResponse
    {
        $periode = PeriodePaie::on('tenant')->withCount('paies')->findOrFail($id);

        return response()->json(['data' => $periode]);
    }

    public function update(StorePeriodeRequest $request, int $id): JsonResponse
    {
        $periode = PeriodePaie::on('tenant')->findOrFail($id);

        if (! $periode->isModifiable()) {
            return response()->json(['message' => 'Période clôturée, non modifiable'], 422);
        }

        $periode->update($request->validated());

        return response()->json(['message' => 'Période mise à jour', 'data' => $periode->refresh()]);
    }

    /**
     * Calcule (ou recalcule) les paies de tous les professeurs de la période.
     */
    public function calculer(Request $request, int $id): JsonResponse
    {
        $periode = PeriodePaie::on('tenant')->findOrFail($id);

        if (in_array($periode->statut, ['PAYEE', 'CLOTUREE'], true)) {
            return response()->json(['message' => 'Période déjà payée ou clôturée'], 422);
        }

        $periode->update(['statut' => 'EN_CALCUL']);

        $result = $this->service->genererPaiesPourPeriode(
            $periode,
            $request->input('professeur_ids'),
            (bool) $request->input('forcer', false)
        );

        return response()->json([
            'message' => "Calcul terminé : {$result['generated']} paie(s) générée(s), {$result['skipped']} ignorée(s)",
            'data' => $result,
        ]);
    }

    /**
     * Valide en lot toutes les paies calculées de la période.
     */
    public function valider(int $id): JsonResponse
    {
        $periode = PeriodePaie::on('tenant')->findOrFail($id);

        $count = $this->service->validerPeriode($periode);

        PayrollAuditLogger::log('VALIDATION_PERIODE', 'periode_paie', $periode->id);

        return response()->json([
            'message' => "{$count} paie(s) validée(s)",
            'data' => $periode->refresh(),
        ]);
    }

    public function marquerPayee(int $id): JsonResponse
    {
        $periode = PeriodePaie::on('tenant')->findOrFail($id);
        $periode->update(['statut' => 'PAYEE']);

        return response()->json(['message' => 'Période marquée comme payée', 'data' => $periode]);
    }

    public function cloturer(int $id): JsonResponse
    {
        $periode = PeriodePaie::on('tenant')->findOrFail($id);
        $periode->update(['statut' => 'CLOTUREE']);

        PayrollAuditLogger::log('CLOTURE_PERIODE', 'periode_paie', $periode->id);

        return response()->json(['message' => 'Période clôturée', 'data' => $periode]);
    }
}
