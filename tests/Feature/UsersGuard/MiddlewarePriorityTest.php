<?php

namespace Tests\Feature\UsersGuard;

use App\Http\Middleware\TenantSanctumAuth;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\TestCase;

class MiddlewarePriorityTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function boundTenantRoutes(): array
    {
        return [
            'student binding' => ['api/admin/enrollment/students/{student}'],
            'user binding' => ['api/admin/users/{user}'],
            'exam session binding' => ['api/admin/exams/sessions/{session}'],
        ];
    }

    #[Test]
    #[DataProvider('boundTenantRoutes')]
    public function tenancy_and_tenant_auth_run_before_route_model_binding(string $uri): void
    {
        $router = $this->app->make(Router::class);

        /** @var Route|null $route */
        $route = collect($router->getRoutes()->getRoutes())
            ->first(fn (Route $candidate): bool => $candidate->uri() === $uri && in_array('GET', $candidate->methods(), true));

        $this->assertNotNull($route, "Route {$uri} not found");

        $middleware = array_values($router->gatherRouteMiddleware($route));

        $tenancyIndex = array_search(InitializeTenancyByDomain::class, $middleware, true);
        $authIndex = array_search(TenantSanctumAuth::class, $middleware, true);
        $bindingsIndex = array_search(SubstituteBindings::class, $middleware, true);

        $this->assertNotFalse($tenancyIndex);
        $this->assertNotFalse($authIndex);
        $this->assertNotFalse($bindingsIndex);
        $this->assertLessThan($authIndex, $tenancyIndex);
        $this->assertLessThan($bindingsIndex, $authIndex);
    }

    #[Test]
    public function every_tenant_route_with_parameters_initializes_tenancy_before_binding(): void
    {
        $router = $this->app->make(Router::class);

        $routes = collect($router->getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => str_contains($route->uri(), '{'));

        foreach ($routes as $route) {
            $middleware = array_values($router->gatherRouteMiddleware($route));
            $tenancyIndex = array_search(InitializeTenancyByDomain::class, $middleware, true);
            $bindingsIndex = array_search(SubstituteBindings::class, $middleware, true);

            if ($tenancyIndex === false || $bindingsIndex === false) {
                continue;
            }

            $this->assertLessThan(
                $bindingsIndex,
                $tenancyIndex,
                "Tenancy must run before bindings on {$route->uri()}"
            );
        }

        $this->assertTrue(true);
    }
}
