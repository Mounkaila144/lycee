<?php

namespace Modules\TeacherPayroll\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TeacherPayroll\Entities\Professeur;
use Modules\TeacherPayroll\Http\Requests\StoreProfesseurRequest;
use Modules\TeacherPayroll\Http\Requests\UpdateProfesseurRequest;
use Modules\TeacherPayroll\Services\PayrollAuditLogger;
use Modules\UsersGuard\Entities\TenantUser;

class ProfesseurController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Professeur::on('tenant')->with(['contratActif', 'user:id,username,firstname,lastname,email']);

        if (($actif = $request->input('actif')) !== null) {
            $query->where('actif', filter_var($actif, FILTER_VALIDATE_BOOLEAN));
        }

        if ($statut = $request->input('statut_professeur')) {
            $query->where('statut_professeur', $statut);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return response()->json($query->orderBy('nom')->paginate($request->input('per_page', 15)));
    }

    public function store(StoreProfesseurRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['actif'] = $data['actif'] ?? true;

        $professeur = Professeur::on('tenant')->create($data);

        PayrollAuditLogger::log('CREATION_PROFESSEUR', 'professeur', $professeur->id, null, $data);

        return response()->json([
            'message' => 'Professeur créé avec succès',
            'data' => $professeur,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $professeur = Professeur::on('tenant')
            ->with(['contrats', 'contratActif', 'affectations.classe', 'affectations.matiere'])
            ->findOrFail($id);

        return response()->json(['data' => $professeur]);
    }

    public function update(UpdateProfesseurRequest $request, int $id): JsonResponse
    {
        $professeur = Professeur::on('tenant')->findOrFail($id);
        $ancien = $professeur->toArray();

        $professeur->update($request->validated());

        PayrollAuditLogger::log('MODIFICATION_PROFESSEUR', 'professeur', $professeur->id, $ancien, $professeur->toArray());

        return response()->json([
            'message' => 'Professeur mis à jour avec succès',
            'data' => $professeur->refresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $professeur = Professeur::on('tenant')->findOrFail($id);

        if ($professeur->paies()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer un professeur ayant des fiches de paie. Désactivez-le plutôt.',
            ], 422);
        }

        $professeur->delete();

        return response()->json(['message' => 'Professeur supprimé avec succès'], 200);
    }

    /**
     * Liste les utilisateurs ayant le rôle Professeur qui ne sont pas encore
     * liés à une fiche de paie. C'est la source d'identité canonique.
     */
    public function candidatsUtilisateurs(Request $request): JsonResponse
    {
        $dejaLies = Professeur::on('tenant')->whereNotNull('user_id')->pluck('user_id')->all();

        $query = TenantUser::query()
            ->role('Professeur')
            ->active()
            ->whereNotIn('id', $dejaLies);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('lastname')->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'username' => $u->username,
                'firstname' => $u->firstname,
                'lastname' => $u->lastname,
                'email' => $u->email,
                'phone' => $u->phone ?? $u->mobile,
            ]);

        return response()->json(['data' => $users]);
    }

    /**
     * Crée une fiche de paie liée à un utilisateur (rôle Professeur) existant,
     * en reprenant ses informations. C'est la voie recommandée (pas de doublon).
     */
    public function importerUtilisateur(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'statut_professeur' => ['nullable', 'in:'.implode(',', Professeur::STATUTS)],
        ]);

        $user = TenantUser::query()->role('Professeur')->find($validated['user_id']);
        if (! $user) {
            return response()->json(['message' => "Cet utilisateur n'existe pas ou n'a pas le rôle Professeur."], 422);
        }

        $existante = Professeur::on('tenant')->where('user_id', $user->id)->first();
        if ($existante) {
            return response()->json(['message' => 'Une fiche existe déjà pour cet utilisateur.', 'data' => $existante], 422);
        }

        $professeur = Professeur::on('tenant')->create([
            'user_id' => $user->id,
            'nom' => $user->lastname ?: $user->username,
            'prenom' => $user->firstname ?: '',
            'email' => $user->email,
            'telephone' => $user->phone ?? $user->mobile,
            'statut_professeur' => $validated['statut_professeur'] ?? 'PERMANENT',
            'actif' => true,
        ]);

        PayrollAuditLogger::log('IMPORT_PROFESSEUR', 'professeur', $professeur->id, null, ['user_id' => $user->id]);

        return response()->json(['message' => 'Professeur lié avec succès', 'data' => $professeur], 201);
    }

    /**
     * Lie en lot tous les utilisateurs Professeur non encore liés.
     */
    public function importerTous(): JsonResponse
    {
        $dejaLies = Professeur::on('tenant')->whereNotNull('user_id')->pluck('user_id')->all();

        $users = TenantUser::query()->role('Professeur')->active()->whereNotIn('id', $dejaLies)->get();

        $count = 0;
        foreach ($users as $user) {
            Professeur::on('tenant')->create([
                'user_id' => $user->id,
                'nom' => $user->lastname ?: $user->username,
                'prenom' => $user->firstname ?: '',
                'email' => $user->email,
                'telephone' => $user->phone ?? $user->mobile,
                'statut_professeur' => 'PERMANENT',
                'actif' => true,
            ]);
            $count++;
        }

        if ($count > 0) {
            PayrollAuditLogger::log('IMPORT_PROFESSEURS_LOT', 'professeur', null, null, ['count' => $count]);
        }

        return response()->json(['message' => "{$count} professeur(s) lié(s)", 'data' => ['count' => $count]]);
    }

    /**
     * Activer / désactiver un professeur.
     */
    public function toggleActif(int $id): JsonResponse
    {
        $professeur = Professeur::on('tenant')->findOrFail($id);
        $professeur->update(['actif' => ! $professeur->actif]);

        return response()->json([
            'message' => $professeur->actif ? 'Professeur activé' : 'Professeur désactivé',
            'data' => $professeur,
        ]);
    }
}
