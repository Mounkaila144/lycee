<?php

namespace Modules\UsersGuard\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsersGuard\ManagePermissionsRequest;
use App\Http\Requests\UsersGuard\ManageRolesRequest;
use App\Http\Requests\UsersGuard\StoreUserRequest;
use App\Http\Requests\UsersGuard\UpdateUserRequest;
use App\Http\Resources\UsersGuard\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\UsersGuard\Entities\TenantUser;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $application = $request->input('application');
        $isActive = $request->input('is_active');

        $query = TenantUser::query()->with(['roles', 'permissions']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%");
            });
        }

        if ($application) {
            $query->where('application', $application);
        }

        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        $users = $query->latest()->paginate($perPage);

        return UserResource::collection($users);
    }

    /**
     * Get teachers (users with "Professeur" role).
     *
     * Returns a paginated list of active users who have the "Professeur" role.
     * Supports search functionality across firstname, lastname, email, and username.
     *
     *
     * @example GET /api/admin/teachers?per_page=20&search=Dupont
     */
    public function teachers(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');

        $query = TenantUser::query()
            ->role('Professeur')
            ->active()
            ->with(['roles', 'permissions']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $teachers = $query->latest()->paginate($perPage);

        return UserResource::collection($teachers);
    }

    /**
     * Get students (users with "Étudiant" role).
     *
     * Returns a paginated list of active users who have the "Étudiant" role.
     * Supports search functionality and advanced filtering.
     *
     * @example GET /api/admin/students?per_page=20&search=Dupont&program_id=5
     */
    public function students(Request $request): AnonymousResourceCollection
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $programId = $request->input('program_id');
        $levelId = $request->input('level_id');
        $status = $request->input('status');

        $query = TenantUser::query()
            ->role('Étudiant')
            ->active()
            ->with(['roles', 'permissions']);

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Advanced filters (if Student model has relationships)
        if ($programId) {
            $query->whereHas('enrollments', fn ($q) => $q->where('program_id', $programId));
        }

        if ($levelId) {
            $query->whereHas('enrollments', fn ($q) => $q->where('level_id', $levelId));
        }

        if ($status) {
            $query->where('status', $status);
        }

        $students = $query->latest()->paginate($perPage);

        return UserResource::collection($students);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['password'] = bcrypt($data['password']);
        $data['is_active'] = $data['is_active'] ?? true;

        $user = TenantUser::create($data);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        return response()->json([
            'message' => 'Utilisateur créé avec succès.',
            'user' => new UserResource($user->load(['roles', 'permissions'])),
        ], 201);
    }

    /**
     * Display the specified user.
     */
    public function show(string $id): JsonResponse
    {
        $user = TenantUser::with(['roles', 'permissions'])->findOrFail($id);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $user = TenantUser::findOrFail($id);

        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        $user->update($data);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        return response()->json([
            'message' => 'Utilisateur modifié avec succès.',
            'user' => new UserResource($user->load(['roles', 'permissions'])),
        ]);
    }

    /**
     * Remove the specified user (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $user = TenantUser::findOrFail($id);

        $user->delete();

        return response()->json([
            'message' => 'Utilisateur supprimé avec succès.',
        ]);
    }

    /**
     * Restore a soft deleted user.
     */
    public function restore(string $id): JsonResponse
    {
        $user = TenantUser::onlyTrashed()->findOrFail($id);

        $user->restore();

        return response()->json([
            'message' => 'Utilisateur restauré avec succès.',
            'user' => new UserResource($user->load(['roles', 'permissions'])),
        ]);
    }

    /**
     * Permanently delete a user.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $user = TenantUser::withTrashed()->findOrFail($id);

        $user->forceDelete();

        return response()->json([
            'message' => 'Utilisateur supprimé définitivement.',
        ]);
    }

    /**
     * Add permissions to a user.
     */
    public function addPermissions(ManagePermissionsRequest $request, string $id): JsonResponse
    {
        $user = TenantUser::findOrFail($id);

        $permissions = $request->validated()['permissions'];

        $user->givePermissionTo($permissions);

        return response()->json([
            'message' => 'Permissions ajoutées avec succès.',
            'user' => new UserResource($user->load(['roles', 'permissions'])),
        ]);
    }

    /**
     * Remove permissions from a user.
     */
    public function removePermissions(ManagePermissionsRequest $request, string $id): JsonResponse
    {
        $user = TenantUser::findOrFail($id);

        $permissions = $request->validated()['permissions'];

        $user->revokePermissionTo($permissions);

        return response()->json([
            'message' => 'Permissions retirées avec succès.',
            'user' => new UserResource($user->load(['roles', 'permissions'])),
        ]);
    }

    /**
     * Sync user permissions (replace all permissions).
     */
    public function syncPermissions(ManagePermissionsRequest $request, string $id): JsonResponse
    {
        $user = TenantUser::findOrFail($id);

        $permissions = $request->validated()['permissions'];

        $user->syncPermissions($permissions);

        return response()->json([
            'message' => 'Permissions synchronisées avec succès.',
            'user' => new UserResource($user->load(['roles', 'permissions'])),
        ]);
    }

    /**
     * Add roles to a user.
     */
    public function addRoles(ManageRolesRequest $request, string $id): JsonResponse
    {
        $user = TenantUser::findOrFail($id);
        $roles = $request->validated()['roles'];

        $user->assignRole($roles);

        return response()->json([
            'message' => 'Rôles ajoutés avec succès.',
            'user' => new UserResource($user->load(['roles', 'permissions'])),
        ]);
    }

    /**
     * Remove roles from a user.
     */
    public function removeRoles(ManageRolesRequest $request, string $id): JsonResponse
    {
        $user = TenantUser::findOrFail($id);
        $roles = $request->validated()['roles'];

        // Prevent removing Administrator role from self
        if ($user->id === auth()->id() && in_array('Administrator', $roles)) {
            return response()->json([
                'message' => 'Vous ne pouvez pas retirer votre propre rôle Administrator.',
            ], 403);
        }

        $user->removeRole($roles);

        return response()->json([
            'message' => 'Rôles retirés avec succès.',
            'user' => new UserResource($user->load(['roles', 'permissions'])),
        ]);
    }

    /**
     * Sync user roles (replace all roles).
     */
    public function syncRoles(ManageRolesRequest $request, string $id): JsonResponse
    {
        $user = TenantUser::findOrFail($id);
        $roles = $request->validated()['roles'];

        // Prevent removing Administrator role from self
        if ($user->id === auth()->id() && ! in_array('Administrator', $roles)) {
            return response()->json([
                'message' => 'Vous ne pouvez pas retirer votre propre rôle Administrator.',
            ], 403);
        }

        $user->syncRoles($roles);

        return response()->json([
            'message' => 'Rôles synchronisés avec succès.',
            'user' => new UserResource($user->load(['roles', 'permissions'])),
        ]);
    }
}
