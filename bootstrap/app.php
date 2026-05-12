<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register global middleware
        // TODO: Install laravel/sanctum to enable this middleware
        // $middleware->api(prepend: [
        //     \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        // ]);

        $middleware->alias([
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'tenant' => \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            'tenant.header' => \Stancl\Tenancy\Middleware\InitializeTenancyByRequestData::class,
            'auth.api' => \App\Http\Middleware\AuthenticateApi::class,
            'tenant.auth' => \App\Http\Middleware\TenantSanctumAuth::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Force JSON responses for API routes
        $exceptions->shouldRenderJsonWhen(function ($request, $throwable) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Handle unauthenticated requests
        $exceptions->respond(function ($response, $throwable, $request) {
            if ($throwable instanceof \Illuminate\Auth\AuthenticationException && $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return $response;
        });

        // Forbidden: user authenticated but lacks role/permission (Spatie Permission)
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'FORBIDDEN',
                    'message' => "Vous n'avez pas les droits requis pour cette action.",
                    'required_roles' => $e->getRequiredRoles() ?? [],
                    'required_permissions' => $e->getRequiredPermissions() ?? [],
                ], 403);
            }
        });
    })->create();
