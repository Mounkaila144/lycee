<?php

namespace Modules\UsersGuard\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsersGuard\StoreSuperAdminRequest;
use App\Http\Requests\UsersGuard\UpdateSuperAdminRequest;
use App\Http\Resources\UsersGuard\SuperAdminResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\UsersGuard\Entities\SuperAdmin;

class UserController extends Controller
{
    /**
     * Display a listing of the super admin users.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $isActive = $request->input('is_active');

        $query = SuperAdmin::query()->superadmin();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%");
            });
        }

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        $users = $query->latest()->paginate($perPage);

        return SuperAdminResource::collection($users);
    }

    /**
     * Store a newly created super admin user.
     */
    public function store(StoreSuperAdminRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['password'] = bcrypt($data['password']);
        $data['application'] = 'superadmin';
        $data['is_active'] = $data['is_active'] ?? true;

        $user = SuperAdmin::create($data);

        return response()->json([
            'message' => 'Super administrateur créé avec succès.',
            'user' => new SuperAdminResource($user),
        ], 201);
    }

    /**
     * Display the specified super admin user.
     */
    public function show(string $id): JsonResponse
    {
        $user = SuperAdmin::superadmin()->findOrFail($id);

        return response()->json([
            'user' => new SuperAdminResource($user),
        ]);
    }

    /**
     * Update the specified super admin user.
     */
    public function update(UpdateSuperAdminRequest $request, string $id): JsonResponse
    {
        $user = SuperAdmin::superadmin()->findOrFail($id);

        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Super administrateur modifié avec succès.',
            'user' => new SuperAdminResource($user),
        ]);
    }

    /**
     * Remove the specified super admin user (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $user = SuperAdmin::superadmin()->findOrFail($id);

        $user->delete();

        return response()->json([
            'message' => 'Super administrateur supprimé avec succès.',
        ]);
    }

    /**
     * Restore a soft deleted super admin user.
     */
    public function restore(string $id): JsonResponse
    {
        $user = SuperAdmin::superadmin()->onlyTrashed()->findOrFail($id);

        $user->restore();

        return response()->json([
            'message' => 'Super administrateur restauré avec succès.',
            'user' => new SuperAdminResource($user),
        ]);
    }

    /**
     * Permanently delete a super admin user.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $user = SuperAdmin::superadmin()->withTrashed()->findOrFail($id);

        $user->forceDelete();

        return response()->json([
            'message' => 'Super administrateur supprimé définitivement.',
        ]);
    }

    /**
     * Toggle user active status.
     */
    public function toggleActive(string $id): JsonResponse
    {
        $user = SuperAdmin::superadmin()->findOrFail($id);

        $user->update(['is_active' => ! $user->is_active]);

        return response()->json([
            'message' => $user->is_active ? 'Utilisateur activé avec succès.' : 'Utilisateur désactivé avec succès.',
            'user' => new SuperAdminResource($user),
        ]);
    }
}
