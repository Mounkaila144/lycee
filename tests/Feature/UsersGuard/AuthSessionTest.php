<?php

namespace Tests\Feature\UsersGuard;

use Laravel\Sanctum\PersonalAccessToken;
use Modules\UsersGuard\Entities\TenantUser;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class AuthSessionTest extends TestCase
{
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Log a user in through the API and return the issued token.
     */
    private function loginAs(string $role): string
    {
        $user = TenantUser::factory()->create(['password' => bcrypt('password')]);
        $this->assignRole($user, $role);

        return $this->postJson('/api/admin/auth/login', [
            'username' => $user->username,
            'password' => 'password',
            'application' => 'admin',
        ])->assertOk()->json('data.token');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function roleHomeRoutes(): array
    {
        return [
            'Administrator' => ['Administrator', '/admin/dashboard'],
            'Manager' => ['Manager', '/admin/dashboard'],
            'Professeur' => ['Professeur', '/admin/dashboard'],
            'Élève' => ['Élève', '/admin/student/home'],
            'Parent' => ['Parent', '/admin/parent/home'],
            'Caissier' => ['Caissier', '/admin/finance/payments'],
            'Comptable' => ['Comptable', '/admin/finance/reports'],
        ];
    }

    #[Test]
    #[DataProvider('roleHomeRoutes')]
    public function me_returns_primary_role_and_home_route_for_each_role(string $role, string $homeRoute): void
    {
        $token = $this->loginAs($role);

        $this->withToken($token)->getJson('/api/admin/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.primary_role', $role)
            ->assertJsonPath('data.user.home_route', $homeRoute);
    }

    #[Test]
    public function login_rejects_wrong_password(): void
    {
        $user = TenantUser::factory()->create(['password' => bcrypt('password')]);

        $this->postJson('/api/admin/auth/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
            'application' => 'admin',
        ])->assertUnprocessable()->assertJsonValidationErrors('username');
    }

    #[Test]
    public function login_rejects_inactive_user(): void
    {
        $user = TenantUser::factory()->inactive()->create(['password' => bcrypt('password')]);

        $this->postJson('/api/admin/auth/login', [
            'username' => $user->username,
            'password' => 'password',
            'application' => 'admin',
        ])->assertUnprocessable();
    }

    #[Test]
    public function logout_revokes_the_current_token(): void
    {
        $token = $this->loginAs('Élève');
        [$tokenId] = explode('|', $token, 2);

        $this->withToken($token)->postJson('/api/admin/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull(PersonalAccessToken::on('tenant')->find($tokenId));
    }

    #[Test]
    public function refresh_replaces_the_current_token(): void
    {
        $token = $this->loginAs('Professeur');
        [$tokenId] = explode('|', $token, 2);

        $newToken = $this->withToken($token)->postJson('/api/admin/auth/refresh')
            ->assertOk()
            ->json('data.token');

        $this->assertNotSame($token, $newToken);
        $this->assertNull(PersonalAccessToken::on('tenant')->find($tokenId));
    }

    #[Test]
    public function malformed_bearer_token_returns_401_instead_of_500(): void
    {
        $this->withToken('not-a-sanctum-token')->getJson('/api/admin/auth/me')
            ->assertUnauthorized();
    }

    #[Test]
    public function me_requires_authentication(): void
    {
        $this->getJson('/api/admin/auth/me')->assertUnauthorized();
    }

    #[Test]
    public function user_models_never_serialize_secrets(): void
    {
        $tenantUser = TenantUser::factory()->create(['remember_token' => 'secret-remember-token']);
        $user = User::query()->findOrFail($tenantUser->id);

        foreach ([$tenantUser->fresh()->toArray(), $user->toArray()] as $serialized) {
            $this->assertArrayNotHasKey('password', $serialized);
            $this->assertArrayNotHasKey('remember_token', $serialized);
        }
    }
}
