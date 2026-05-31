<?php

namespace Modules\TeacherPayroll\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TeacherPayroll\Entities\ContratProfesseur;
use Modules\TeacherPayroll\Entities\Professeur;
use Modules\TeacherPayroll\Http\Requests\StoreContratRequest;
use Modules\TeacherPayroll\Services\PayrollAuditLogger;

class ContratController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContratProfesseur::on('tenant')->with('professeur');

        if ($professeurId = $request->input('professeur_id')) {
            $query->where('professeur_id', $professeurId);
        }

        if ($statut = $request->input('statut')) {
            $query->where('statut', $statut);
        }

        return response()->json(['data' => $query->orderBy('date_debut', 'desc')->get()]);
    }

    /**
     * Configuration du mode de paiement : crée un contrat pour un professeur.
     * Un seul contrat ACTIF par professeur et par année scolaire.
     */
    public function store(StoreContratRequest $request, int $professeurId): JsonResponse
    {
        $professeur = Professeur::on('tenant')->findOrFail($professeurId);

        $data = $request->validated();
        $data['professeur_id'] = $professeur->id;
        $data['statut'] = $data['statut'] ?? 'ACTIF';
        $data['devise'] = $data['devise'] ?? config('teacherpayroll.currency', 'FCFA');

        if ($data['statut'] === 'ACTIF') {
            $this->desactiverContratsActifs($professeur->id, $data['annee_scolaire_id'] ?? null);
        }

        $contrat = ContratProfesseur::on('tenant')->create($data);

        PayrollAuditLogger::log('CREATION_CONTRAT', 'contrat_professeur', $contrat->id, null, $data);

        return response()->json([
            'message' => 'Contrat créé avec succès',
            'data' => $contrat,
        ], 201);
    }

    public function update(StoreContratRequest $request, int $id): JsonResponse
    {
        $contrat = ContratProfesseur::on('tenant')->findOrFail($id);
        $ancien = $contrat->toArray();

        $contrat->update($request->validated());

        PayrollAuditLogger::log('MODIFICATION_CONTRAT', 'contrat_professeur', $contrat->id, $ancien, $contrat->toArray());

        return response()->json([
            'message' => 'Contrat mis à jour avec succès',
            'data' => $contrat->refresh(),
        ]);
    }

    public function activate(int $id): JsonResponse
    {
        $contrat = ContratProfesseur::on('tenant')->findOrFail($id);
        $this->desactiverContratsActifs($contrat->professeur_id, $contrat->annee_scolaire_id, $contrat->id);
        $contrat->update(['statut' => 'ACTIF']);

        return response()->json(['message' => 'Contrat activé', 'data' => $contrat]);
    }

    public function terminate(Request $request, int $id): JsonResponse
    {
        $contrat = ContratProfesseur::on('tenant')->findOrFail($id);
        $contrat->update([
            'statut' => 'TERMINE',
            'date_fin' => $request->input('date_fin', now()->toDateString()),
        ]);

        return response()->json(['message' => 'Contrat terminé', 'data' => $contrat]);
    }

    private function desactiverContratsActifs(int $professeurId, ?int $anneeScolaireId, ?int $exceptId = null): void
    {
        ContratProfesseur::on('tenant')
            ->where('professeur_id', $professeurId)
            ->where('statut', 'ACTIF')
            ->when($anneeScolaireId !== null, fn ($q) => $q->where('annee_scolaire_id', $anneeScolaireId))
            ->when($exceptId !== null, fn ($q) => $q->where('id', '!=', $exceptId))
            ->update(['statut' => 'SUSPENDU']);
    }
}
