<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

/**
 * Identifie l'école par son domaine (comportement stancl inchangé), puis refuse
 * l'accès si le super-admin l'a désactivée (is_active === false ; absent = active).
 *
 * Lié au conteneur à la place de InitializeTenancyByDomain (AppServiceProvider) :
 * l'alias « tenant », la liste de priorité et withoutMiddleware() des tests
 * continuent de référencer la classe stancl.
 */
class InitializeActiveTenancyByDomain extends InitializeTenancyByDomain
{
    /**
     * @param  Request  $request
     */
    public function handle($request, Closure $next): mixed
    {
        return parent::handle($request, function (Request $request) use ($next): mixed {
            if (tenancy()->initialized && ! tenant()->isActive()) {
                return response()->json([
                    'success' => false,
                    'error' => 'TENANT_INACTIVE',
                    'message' => 'Établissement désactivé. Contactez l\'administrateur de la plateforme.',
                ], 403);
            }

            return $next($request);
        });
    }
}
