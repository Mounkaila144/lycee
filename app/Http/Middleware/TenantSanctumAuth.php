<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class TenantSanctumAuth
{
    public function handle(Request $request, Closure $next, string $guard = 'tenant'): Response
    {
        // Récupérer le token du header Authorization
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Extraire l'ID et le token
        [$id, $plainTextToken] = explode('|', $token, 2);

        // Utiliser la connexion tenant pour chercher le token
        $accessToken = PersonalAccessToken::on('tenant')
            ->where('id', $id)
            ->first();

        if (! $accessToken || ! hash_equals($accessToken->token, hash('sha256', $plainTextToken))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Vérifier si le token est expiré
        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Token expired.',
            ], 401);
        }

        // Authentifier l'utilisateur
        $user = $accessToken->tokenable;

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 401);
        }

        // Définir l'utilisateur authentifié
        $request->setUserResolver(fn () => $user);

        // Mettre à jour last_used_at
        $accessToken->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
