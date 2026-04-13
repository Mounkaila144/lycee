<?php

namespace Modules\UsersGuard\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\UsersGuard\Entities\TenantUser;
use Modules\UsersGuard\Http\Requests\TenantLoginRequest;

class AuthController extends Controller
{
    /**
     * Login TENANT (tenant database)
     * POST /api/admin/auth/login
     * Header required: X-Tenant-ID or domain-based tenancy
     */
    public function login(TenantLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = TenantUser::where('username', $validated['username'])
            ->where('application', $validated['application'])
            ->active()
            ->first();

        if (!$user || !$this->checkPassword($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Invalid credentials'],
            ]);
        }

        $token = $user->createToken('tenant-token', [
            'role:' . $validated['application'],
            'tenant:' . tenancy()->tenant->getTenantKey(),
        ])->plainTextToken;

        $user->load(['roles.permissions', 'permissions']);

        $user->updateLastLogin();

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'full_name' => $user->full_name,
                    'application' => $user->application,
                    'avatar_url' => $user->avatar_url,
                    'roles' => $user->roles,
                    'permissions' => $user->permissions,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'tenant' => [
                    'id' => tenancy()->tenant->getTenantKey(),
                    'name' => tenancy()->tenant->company_name,
                ],
            ],
        ]);
    }

    /**
     * Get current user (admin or frontend)
     * GET /api/admin/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->load(['roles.permissions', 'permissions']);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'full_name' => $user->full_name,
                    'application' => $user->application,
                    'is_active' => $user->is_active,
                    'avatar_url' => $user->avatar_url,
                    'lastlogin' => $user->lastlogin,
                    'roles' => $user->roles,
                    'permissions' => $user->getAllPermissions(),
                ],
                'tenant' => [
                    'id' => tenancy()->tenant->getTenantKey(),
                    'name' => tenancy()->tenant->company_name,
                ],
            ],
        ]);
    }

    /**
     * Logout
     * POST /api/admin/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ]);
    }

    /**
     * Refresh token
     * POST /api/admin/auth/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->user()->currentAccessToken()->delete();

        $token = $user->createToken('tenant-token', [
            'role:' . $user->application,
            'tenant:' . tenancy()->tenant->getTenantKey(),
        ])->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Check password (supports MD5 legacy and bcrypt)
     */
    private function checkPassword(string $plainPassword, string $hashedPassword): bool
    {
        if (strlen($hashedPassword) === 60) {
            return Hash::check($plainPassword, $hashedPassword);
        }

        if (strlen($hashedPassword) === 32) {
            return md5($plainPassword) === $hashedPassword;
        }

        return false;
    }
}
