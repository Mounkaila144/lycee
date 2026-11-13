<?php

namespace Tests\Feature\UsersGuard;

use Illuminate\Support\Facades\Route;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class DefaultGuardResolutionTest extends TestCase
{
    use InteractsWithTenancy;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        Route::middleware(['tenant.auth'])->get('/api/_test/default-guard', fn (): array => [
            'auth_id' => auth()->id(),
            'default_guard' => auth()->getDefaultDriver(),
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    #[Test]
    public function auth_helper_resolves_tenant_user_after_tenant_sanctum_auth(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/_test/default-guard');

        $response->assertOk()
            ->assertJson([
                'auth_id' => $this->user->id,
                'default_guard' => 'tenant',
            ]);
    }
}
