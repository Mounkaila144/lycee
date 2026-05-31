<?php

namespace Modules\TeacherPayroll\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TeacherPayroll\Entities\AffectationProfesseur;
use Modules\TeacherPayroll\Http\Requests\StoreAffectationRequest;

class AffectationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AffectationProfesseur::on('tenant')->with(['professeur', 'classe', 'matiere']);

        if ($professeurId = $request->input('professeur_id')) {
            $query->where('professeur_id', $professeurId);
        }

        if ($classeId = $request->input('classe_id')) {
            $query->where('classe_id', $classeId);
        }

        if (($actif = $request->input('actif')) !== null) {
            $query->where('actif', filter_var($actif, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(StoreAffectationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['actif'] = $data['actif'] ?? true;

        $affectation = AffectationProfesseur::on('tenant')->create($data);

        return response()->json([
            'message' => 'Affectation créée avec succès',
            'data' => $affectation->load(['classe', 'matiere']),
        ], 201);
    }

    public function update(StoreAffectationRequest $request, int $id): JsonResponse
    {
        $affectation = AffectationProfesseur::on('tenant')->findOrFail($id);
        $affectation->update($request->validated());

        return response()->json([
            'message' => 'Affectation mise à jour',
            'data' => $affectation->refresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $affectation = AffectationProfesseur::on('tenant')->findOrFail($id);
        $affectation->delete();

        return response()->json(['message' => 'Affectation supprimée'], 200);
    }
}
