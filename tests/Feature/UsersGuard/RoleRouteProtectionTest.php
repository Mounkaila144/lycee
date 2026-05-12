<?php

namespace Tests\Feature\UsersGuard;

use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class RoleRouteProtectionTest extends TestCase
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

    private function tokenFor(string $role): string
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, $role);

        return $user->createToken('test-token')->plainTextToken;
    }

    #[Test]
    public function administrator_can_list_users(): void
    {
        $token = $this->tokenFor('Administrator');

        $this->withToken($token)->getJson('/api/admin/users')->assertOk();
    }

    #[Test]
    public function manager_can_list_users(): void
    {
        $token = $this->tokenFor('Manager');

        $this->withToken($token)->getJson('/api/admin/users')->assertOk();
    }

    #[Test]
    public function etudiant_cannot_list_users(): void
    {
        $token = $this->tokenFor('Étudiant');

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertForbidden()
            ->assertJsonPath('error', 'FORBIDDEN');
    }

    #[Test]
    public function professeur_cannot_list_users(): void
    {
        $token = $this->tokenFor('Professeur');

        $this->withToken($token)->getJson('/api/admin/users')->assertForbidden();
    }

    #[Test]
    public function caissier_cannot_list_users(): void
    {
        $token = $this->tokenFor('Caissier');

        $this->withToken($token)->getJson('/api/admin/users')->assertForbidden();
    }

    #[Test]
    public function manager_cannot_delete_users(): void
    {
        $other = TenantUser::factory()->create(['application' => 'admin']);
        $token = $this->tokenFor('Manager');

        $this->withToken($token)
            ->deleteJson("/api/admin/users/{$other->id}")
            ->assertForbidden();
    }

    #[Test]
    public function administrator_can_list_roles(): void
    {
        $token = $this->tokenFor('Administrator');

        $this->withToken($token)->getJson('/api/admin/roles')->assertOk();
    }

    #[Test]
    public function manager_cannot_list_roles(): void
    {
        $token = $this->tokenFor('Manager');

        $this->withToken($token)->getJson('/api/admin/roles')->assertForbidden();
    }

    #[Test]
    public function unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/admin/users')->assertStatus(401);
    }

    #[Test]
    public function professeur_can_access_teachers_endpoint(): void
    {
        $token = $this->tokenFor('Professeur');

        $this->withToken($token)->getJson('/api/admin/teachers')->assertOk();
    }

    #[Test]
    public function etudiant_cannot_access_teachers_endpoint(): void
    {
        $token = $this->tokenFor('Étudiant');

        $this->withToken($token)->getJson('/api/admin/teachers')->assertForbidden();
    }

    #[Test]
    public function caissier_can_access_students_endpoint(): void
    {
        $token = $this->tokenFor('Caissier');

        $this->withToken($token)->getJson('/api/admin/students')->assertOk();
    }
}
