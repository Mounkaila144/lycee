<?php

namespace Modules\UsersGuard\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\UsersGuard\Entities\SuperAdmin;
use Modules\UsersGuard\Http\Requests\SuperAdminLoginRequest;

class AuthController extends Controller
{
    /**
     * Login SUPERADMIN (central database)
     * POST /api/superadmin/auth/login
     * No X-Tenant-ID header required
     */
    public function login(SuperAdminLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = SuperAdmin::where('username', $validated['username'])
            ->superadmin()
            ->active()
            ->first();

        if (!$user || !$this->checkPassword($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Invalid credentials'],
            ]);
        }

        $token = $user->createToken('superadmin-token', [
            'role:superadmin',
        ])->plainTextToken;

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
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Get current super admin user
     * GET /api/superadmin/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

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
                    'lastlogin' => $user->lastlogin,
                ],
            ],
        ]);
    }

    /**
     * Logout
     * POST /api/superadmin/auth/logout
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
     * POST /api/superadmin/auth/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->user()->currentAccessToken()->delete();

        $token = $user->createToken('superadmin-token', [
            'role:superadmin',
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
