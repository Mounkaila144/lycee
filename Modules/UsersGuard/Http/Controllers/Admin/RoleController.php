<?php

namespace Modules\UsersGuard\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Get all available roles.
     */
    public function index(): JsonResponse
    {
        $roles = Role::where('guard_name', 'tenant')
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $roles,
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'tenant',
            'display_name' => $validated['display_name'] ?? $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return response()->json([
            'message' => 'Rôle créé avec succès.',
            'data' => $role->load('permissions'),
        ], 201);
    }

    /**
     * Display the specified role.
     */
    public function show(string $id): JsonResponse
    {
        $role = Role::where('guard_name', 'tenant')
            ->with('permissions')
            ->findOrFail($id);

        return response()->json([
            'data' => $role,
        ]);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $role = Role::where('guard_name', 'tenant')->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:roles,name,'.$id],
            'display_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->update([
            'name' => $validated['name'] ?? $role->name,
            'display_name' => $validated['display_name'] ?? $role->display_name,
            'description' => $validated['description'] ?? $role->description,
        ]);

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return response()->json([
            'message' => 'Rôle modifié avec succès.',
            'data' => $role->load('permissions'),
        ]);
    }

    /**
     * Remove the specified role.
     */
    public function destroy(string $id): JsonResponse
    {
        $role = Role::where('guard_name', 'tenant')->findOrFail($id);

        // Prevent deletion of system roles
        $systemRoles = ['Administrator', 'Manager', 'User', 'Professeur', 'Étudiant', 'Caissier', 'Agent Comptable', 'Comptable'];
        if (in_array($role->name, $systemRoles)) {
            return response()->json([
                'message' => 'Impossible de supprimer un rôle système.',
            ], 403);
        }

        // Check if role is assigned to users
        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer ce rôle car il est assigné à des utilisateurs.',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'message' => 'Rôle supprimé avec succès.',
        ]);
    }
}
