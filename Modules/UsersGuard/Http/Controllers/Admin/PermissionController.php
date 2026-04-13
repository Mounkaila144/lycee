<?php

namespace Modules\UsersGuard\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Get all available permissions.
     */
    public function index(): JsonResponse
    {
        $permissions = Permission::where('guard_name', 'tenant')
            ->orderBy('name')
            ->get(['id', 'name', 'display_name']);

        return response()->json([
            'data' => $permissions,
        ]);
    }

    /**
     * Store a newly created permission.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
            'display_name' => ['nullable', 'string', 'max:255'],
        ]);

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => 'tenant',
            'display_name' => $validated['display_name'] ?? ucfirst(str_replace('_', ' ', $validated['name'])),
        ]);

        return response()->json([
            'message' => 'Permission créée avec succès.',
            'data' => $permission,
        ], 201);
    }

    /**
     * Display the specified permission.
     */
    public function show(string $id): JsonResponse
    {
        $permission = Permission::where('guard_name', 'tenant')
            ->findOrFail($id);

        return response()->json([
            'data' => $permission,
        ]);
    }

    /**
     * Update the specified permission.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $permission = Permission::where('guard_name', 'tenant')->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:permissions,name,'.$id],
            'display_name' => ['nullable', 'string', 'max:255'],
        ]);

        $permission->update([
            'name' => $validated['name'] ?? $permission->name,
            'display_name' => $validated['display_name'] ?? $permission->display_name,
        ]);

        return response()->json([
            'message' => 'Permission modifiée avec succès.',
            'data' => $permission,
        ]);
    }

    /**
     * Remove the specified permission.
     */
    public function destroy(string $id): JsonResponse
    {
        $permission = Permission::where('guard_name', 'tenant')->findOrFail($id);

        // Check if permission is assigned to roles or users
        if ($permission->roles()->count() > 0 || $permission->users()->count() > 0) {
            return response()->json([
                'message' => 'Impossible de supprimer cette permission car elle est assignée à des rôles ou utilisateurs.',
            ], 422);
        }

        $permission->delete();

        return response()->json([
            'message' => 'Permission supprimée avec succès.',
        ]);
    }
}
