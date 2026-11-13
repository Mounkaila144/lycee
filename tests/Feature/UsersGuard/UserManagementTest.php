<?php

namespace Tests\Feature\UsersGuard;

use Illuminate\Testing\TestResponse;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $actingUser;

    private string $token;

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

    private function actAs(string $role): void
    {
        $this->actingUser = TenantUser::factory()->create();
        $this->assignRole($this->actingUser, $role);
        $this->token = $this->actingUser->createToken('test-token')->plainTextToken;
    }

    private function authGetJson(string $uri): TestResponse
    {
        return $this->withToken($this->token)->getJson($uri);
    }

    private function authPostJson(string $uri, array $data = []): TestResponse
    {
        return $this->withToken($this->token)->postJson($uri, $data);
    }

    private function authPutJson(string $uri, array $data = []): TestResponse
    {
        return $this->withToken($this->token)->putJson($uri, $data);
    }

    private function authDeleteJson(string $uri): TestResponse
    {
        return $this->withToken($this->token)->deleteJson($uri);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function userPayload(array $overrides = []): array
    {
        return array_merge([
            'username' => 'new.user.'.uniqid(),
            'email' => 'new-'.uniqid().'@example.com',
            'password' => 'secret123',
            'firstname' => 'New',
            'lastname' => 'User',
            'application' => 'admin',
        ], $overrides);
    }

    #[Test]
    public function administrator_can_run_full_user_crud(): void
    {
        $this->actAs('Administrator');

        $userId = $this->authPostJson('/api/admin/users', $this->userPayload(['roles' => ['Professeur']]))
            ->assertCreated()
            ->assertJsonPath('user.roles', ['Professeur'])
            ->json('user.id');

        $this->authGetJson("/api/admin/users/{$userId}")
            ->assertOk()
            ->assertJsonPath('user.id', $userId);

        $this->authPutJson("/api/admin/users/{$userId}", ['firstname' => 'Renamed', 'roles' => ['Administrator']])
            ->assertOk()
            ->assertJsonPath('user.firstname', 'Renamed')
            ->assertJsonPath('user.roles', ['Administrator']);

        $this->authPostJson("/api/admin/users/{$userId}/roles/sync", ['roles' => ['Comptable']])
            ->assertOk();

        $this->authDeleteJson("/api/admin/users/{$userId}")->assertOk();
        $this->assertSoftDeleted('users', ['id' => $userId], 'tenant');
    }

    #[Test]
    public function administrator_can_list_roles_and_permissions(): void
    {
        $this->actAs('Administrator');

        $this->authGetJson('/api/admin/roles')->assertOk();
        $this->authGetJson('/api/admin/permissions')->assertOk();
    }

    #[Test]
    public function manager_can_create_a_teacher(): void
    {
        $this->actAs('Manager');

        $this->authPostJson('/api/admin/users', $this->userPayload(['roles' => ['Professeur']]))
            ->assertCreated()
            ->assertJsonPath('user.roles', ['Professeur']);
    }

    #[Test]
    public function manager_cannot_create_an_administrator(): void
    {
        $this->actAs('Manager');

        $this->authPostJson('/api/admin/users', $this->userPayload(['roles' => ['Administrator']]))
            ->assertForbidden();
    }

    #[Test]
    public function manager_cannot_grant_direct_permissions(): void
    {
        $this->actAs('Manager');

        $this->authPostJson('/api/admin/users', $this->userPayload(['permissions' => ['delete users']]))
            ->assertForbidden();
    }

    #[Test]
    public function manager_cannot_modify_an_administrator_account(): void
    {
        $this->actAs('Manager');

        $admin = TenantUser::factory()->create();
        $this->assignRole($admin, 'Administrator');

        $this->authPutJson("/api/admin/users/{$admin->id}", ['password' => 'hijacked123'])
            ->assertForbidden();
    }

    #[Test]
    public function manager_cannot_promote_a_user_to_administrator(): void
    {
        $this->actAs('Manager');

        $teacher = TenantUser::factory()->create();
        $this->assignRole($teacher, 'Professeur');

        $this->authPutJson("/api/admin/users/{$teacher->id}", ['roles' => ['Administrator']])
            ->assertForbidden();

        $this->assertFalse($teacher->fresh()->hasRole('Administrator'));
    }

    #[Test]
    public function manager_can_update_a_non_administrator_user(): void
    {
        $this->actAs('Manager');

        $teacher = TenantUser::factory()->create();
        $this->assignRole($teacher, 'Professeur');

        $this->authPutJson("/api/admin/users/{$teacher->id}", ['firstname' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('user.firstname', 'Updated');
    }

    #[Test]
    public function student_and_parent_cannot_reach_user_management(): void
    {
        foreach (['Élève', 'Parent'] as $role) {
            $this->actAs($role);

            $this->authGetJson('/api/admin/users')->assertForbidden();
            $this->authGetJson('/api/admin/roles')->assertForbidden();
            $this->authPostJson('/api/admin/users', $this->userPayload())->assertForbidden();
        }
    }
}
