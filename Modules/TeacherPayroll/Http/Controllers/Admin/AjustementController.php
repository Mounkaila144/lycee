<?php

namespace Modules\TeacherPayroll\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TeacherPayroll\Entities\AbsenceProfesseur;
use Modules\TeacherPayroll\Entities\AvanceProfesseur;
use Modules\TeacherPayroll\Entities\PrimeProfesseur;
use Modules\TeacherPayroll\Entities\RetenueProfesseur;
use Modules\TeacherPayroll\Http\Requests\StoreAjustementRequest;
use Modules\TeacherPayroll\Http\Requests\StoreAvanceRequest;
use Modules\TeacherPayroll\Services\PayrollAuditLogger;

/**
 * Gestion des primes, retenues, avances et absences des professeurs.
 */
class AjustementController extends Controller
{
    // ---- Primes -----------------------------------------------------------

    public function indexPrimes(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->filtrer(PrimeProfesseur::on('tenant'), $request)->get()]);
    }

    public function storePrime(StoreAjustementRequest $request): JsonResponse
    {
        $prime = PrimeProfesseur::on('tenant')->create($request->validated());
        PayrollAuditLogger::log('MODIFICATION_PRIME', 'prime_professeur', $prime->id, null, $request->validated());

        return response()->json(['message' => 'Prime ajoutée', 'data' => $prime], 201);
    }

    public function destroyPrime(int $id): JsonResponse
    {
        PrimeProfesseur::on('tenant')->findOrFail($id)->delete();

        return response()->json(['message' => 'Prime supprimée'], 200);
    }

    // ---- Retenues ---------------------------------------------------------

    public function indexRetenues(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->filtrer(RetenueProfesseur::on('tenant'), $request)->get()]);
    }

    public function storeRetenue(StoreAjustementRequest $request): JsonResponse
    {
        $retenue = RetenueProfesseur::on('tenant')->create($request->validated());
        PayrollAuditLogger::log('MODIFICATION_RETENUE', 'retenue_professeur', $retenue->id, null, $request->validated());

        return response()->json(['message' => 'Retenue ajoutée', 'data' => $retenue], 201);
    }

    public function destroyRetenue(int $id): JsonResponse
    {
        RetenueProfesseur::on('tenant')->findOrFail($id)->delete();

        return response()->json(['message' => 'Retenue supprimée'], 200);
    }

    // ---- Avances ----------------------------------------------------------

    public function indexAvances(Request $request): JsonResponse
    {
        $query = AvanceProfesseur::on('tenant')->with('professeur');

        if ($professeurId = $request->input('professeur_id')) {
            $query->where('professeur_id', $professeurId);
        }
        if ($statut = $request->input('statut')) {
            $query->where('statut', $statut);
        }

        return response()->json(['data' => $query->orderBy('date_avance', 'desc')->get()]);
    }

    public function storeAvance(StoreAvanceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['statut'] = 'EN_ATTENTE';

        $avance = AvanceProfesseur::on('tenant')->create($data);

        return response()->json(['message' => 'Avance enregistrée', 'data' => $avance], 201);
    }

    public function approuverAvance(int $id): JsonResponse
    {
        $avance = AvanceProfesseur::on('tenant')->findOrFail($id);
        $avance->update(['statut' => 'APPROUVEE']);
        PayrollAuditLogger::log('APPROBATION_AVANCE', 'avance_professeur', $avance->id);

        return response()->json(['message' => 'Avance approuvée', 'data' => $avance]);
    }

    public function annulerAvance(int $id): JsonResponse
    {
        $avance = AvanceProfesseur::on('tenant')->findOrFail($id);
        $avance->update(['statut' => 'ANNULEE']);

        return response()->json(['message' => 'Avance annulée', 'data' => $avance]);
    }

    // ---- Absences ---------------------------------------------------------

    public function indexAbsences(Request $request): JsonResponse
    {
        $query = AbsenceProfesseur::on('tenant')->with('professeur');

        if ($professeurId = $request->input('professeur_id')) {
            $query->where('professeur_id', $professeurId);
        }

        return response()->json(['data' => $query->orderBy('date_absence', 'desc')->get()]);
    }

    public function storeAbsence(Request $request): JsonResponse
    {
        $data = $request->validate([
            'professeur_id' => ['required', 'integer'],
            'date_absence' => ['required', 'date'],
            'heure_debut' => ['nullable', 'date_format:H:i'],
            'heure_fin' => ['nullable', 'date_format:H:i'],
            'nombre_heures' => ['nullable', 'numeric', 'min:0'],
            'motif' => ['nullable', 'string'],
            'justifiee' => ['nullable', 'boolean'],
            'impact_sur_paie' => ['nullable', 'boolean'],
            'montant_retenue' => ['nullable', 'numeric', 'min:0'],
        ]);

        $absence = AbsenceProfesseur::on('tenant')->create($data);

        return response()->json(['message' => 'Absence enregistrée', 'data' => $absence], 201);
    }

    public function destroyAbsence(int $id): JsonResponse
    {
        AbsenceProfesseur::on('tenant')->findOrFail($id)->delete();

        return response()->json(['message' => 'Absence supprimée'], 200);
    }

    private function filtrer($query, Request $request)
    {
        if ($professeurId = $request->input('professeur_id')) {
            $query->where('professeur_id', $professeurId);
        }
        if ($periodeId = $request->input('periode_paie_id')) {
            $query->where('periode_paie_id', $periodeId);
        }

        return $query->orderBy('created_at', 'desc');
    }
}
