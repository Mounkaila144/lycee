<?php

namespace Modules\TeacherPayroll\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TeacherPayroll\Entities\HeureCoursProfesseur;
use Modules\TeacherPayroll\Http\Requests\StoreHeureCoursRequest;
use Modules\TeacherPayroll\Services\PayrollAuditLogger;

class HeureCoursController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HeureCoursProfesseur::on('tenant')->with(['professeur', 'classe', 'matiere', 'remplacant']);

        if ($professeurId = $request->input('professeur_id')) {
            $query->where('professeur_id', $professeurId);
        }

        if ($statut = $request->input('statut')) {
            $query->where('statut', $statut);
        }

        if ($from = $request->input('date_debut')) {
            $query->whereDate('date_cours', '>=', $from);
        }

        if ($to = $request->input('date_fin')) {
            $query->whereDate('date_cours', '<=', $to);
        }

        return response()->json(['data' => $query->orderBy('date_cours', 'desc')->get()]);
    }

    public function store(StoreHeureCoursRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['statut'] = $data['statut'] ?? 'PREVU';

        $heure = HeureCoursProfesseur::on('tenant')->create($data);

        return response()->json([
            'message' => 'Heure de cours enregistrée',
            'data' => $heure,
        ], 201);
    }

    public function update(StoreHeureCoursRequest $request, int $id): JsonResponse
    {
        $heure = HeureCoursProfesseur::on('tenant')->findOrFail($id);
        $heure->update($request->validated());

        return response()->json(['message' => 'Heure mise à jour', 'data' => $heure->refresh()]);
    }

    /**
     * Valide une ou plusieurs heures (seules les heures VALIDE sont payées).
     */
    public function valider(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (! is_array($ids) || empty($ids)) {
            return response()->json(['message' => 'Aucune heure sélectionnée'], 422);
        }

        $heures = HeureCoursProfesseur::on('tenant')->whereIn('id', $ids)->get();
        foreach ($heures as $heure) {
            $heure->update([
                'statut' => 'VALIDE',
                'valide_par' => auth('tenant')->id(),
                'date_validation' => now(),
            ]);
            PayrollAuditLogger::log('VALIDATION_HEURES', 'heure_cours_professeur', $heure->id);
        }

        return response()->json(['message' => $heures->count().' heure(s) validée(s)']);
    }

    public function rejeter(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (! is_array($ids) || empty($ids)) {
            return response()->json(['message' => 'Aucune heure sélectionnée'], 422);
        }

        $heures = HeureCoursProfesseur::on('tenant')->whereIn('id', $ids)->get();
        foreach ($heures as $heure) {
            $heure->update([
                'statut' => 'REJETE',
                'valide_par' => auth('tenant')->id(),
                'date_validation' => now(),
                'motif' => $request->input('motif', $heure->motif),
            ]);
            PayrollAuditLogger::log('REJET_HEURES', 'heure_cours_professeur', $heure->id);
        }

        return response()->json(['message' => $heures->count().' heure(s) rejetée(s)']);
    }

    /**
     * Marque une heure comme remplacée par un autre professeur.
     */
    public function marquerRemplace(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'remplace_par_professeur_id' => ['required', 'integer'],
            'motif' => ['nullable', 'string'],
        ]);

        $heure = HeureCoursProfesseur::on('tenant')->findOrFail($id);
        $heure->update([
            'statut' => 'REMPLACE',
            'remplace_par_professeur_id' => $request->input('remplace_par_professeur_id'),
            'motif' => $request->input('motif', $heure->motif),
        ]);

        return response()->json(['message' => 'Heure marquée comme remplacée', 'data' => $heure->refresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $heure = HeureCoursProfesseur::on('tenant')->findOrFail($id);
        $heure->delete();

        return response()->json(['message' => 'Heure supprimée'], 200);
    }
}
