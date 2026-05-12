<?php

namespace Modules\UsersGuard\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UsersGuard\UserResource;
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

        // We do NOT filter by `application` anymore: the role hierarchy + home_route
        // determine where the user lands after login. The `application` field on the
        // user model is kept for legacy reasons but is no longer a login gate.
        $user = TenantUser::where('username', $validated['username'])
            ->active()
            ->first();

        if (! $user || ! $this->checkPassword($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Invalid credentials'],
            ]);
        }

        $tenant = tenancy()->tenant;
        $tenantKey = $tenant?->getTenantKey() ?? 'unknown';

        $token = $user->createToken('tenant-token', [
            'role:'.$validated['application'],
            'tenant:'.$tenantKey,
        ])->plainTextToken;

        $user->load(['roles.permissions', 'permissions']);

        $user->updateLastLogin();

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => (new UserResource($user))->toArray($request),
                'token' => $token,
                'token_type' => 'Bearer',
                'tenant' => $tenant ? [
                    'id' => $tenant->getTenantKey(),
                    'name' => $tenant->company_name,
                ] : null,
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

        $tenant = tenancy()->tenant;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => (new UserResource($user))->toArray($request),
                'tenant' => $tenant ? [
                    'id' => $tenant->getTenantKey(),
                    'name' => $tenant->company_name,
                ] : null,
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

        $tenantKey = tenancy()->tenant?->getTenantKey() ?? 'unknown';

        $token = $user->createToken('tenant-token', [
            'role:'.$user->application,
            'tenant:'.$tenantKey,
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
