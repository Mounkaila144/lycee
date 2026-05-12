<?php

namespace Tests\Feature\RoleCoverage\AgentComptable;

use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Protection des routes Agent Comptable — couvre les Stories Agent Comptable 01-06 :
 *   - 01 Home facturation
 *   - 02 Factures CRUD
 *   - 03 Échéanciers
 *   - 04 Pénalités retard
 *   - 05 Recouvrement
 *   - 06 Blocage services (cross-module)
 *
 * RBAC : `role:Administrator|Manager|Comptable|Agent Comptable|Caissier` sur Finance.
 * Restrictions fines (refund, write-off réservés Comptable/Admin) à appliquer story par story.
 */
class AgentComptableRoutesProtectionTest extends TestCase
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
    public function agent_comptable_can_view_own_profile(): void
    {
        $token = $this->tokenFor('Agent Comptable');

        $this->withToken($token)
            ->getJson('/api/admin/auth/me')
            ->assertOk();
    }

    #[Test]
    public function agent_comptable_can_access_finance_routes(): void
    {
        $token = $this->tokenFor('Agent Comptable');

        $response = $this->withToken($token)->getJson('/api/admin/finance/invoices');

        $this->assertNotSame(403, $response->getStatusCode(), 'Agent Comptable doit accéder à Finance (Stories 01-05)');
    }

    #[Test]
    public function agent_comptable_can_access_students_endpoint(): void
    {
        $token = $this->tokenFor('Agent Comptable');

        $this->withToken($token)
            ->getJson('/api/admin/students')
            ->assertOk();
    }

    #[Test]
    public function agent_comptable_cannot_access_teacher_endpoints(): void
    {
        $token = $this->tokenFor('Agent Comptable');

        $this->withToken($token)
            ->getJson('/api/frontend/teacher/my-modules')
            ->assertForbidden();
    }

    #[Test]
    public function agent_comptable_cannot_list_users(): void
    {
        $token = $this->tokenFor('Agent Comptable');

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    #[Test]
    public function agent_comptable_cannot_create_student(): void
    {
        $token = $this->tokenFor('Agent Comptable');

        $this->withToken($token)
            ->postJson('/api/admin/students', [
                'firstname' => 'Test',
                'lastname' => 'Test',
                'birthdate' => '2010-01-01',
                'sex' => 'M',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function agent_comptable_cannot_access_payroll(): void
    {
        $token = $this->tokenFor('Agent Comptable');

        $response = $this->withToken($token)->getJson('/api/admin/payroll/employees');

        $this->assertContains($response->getStatusCode(), [403, 404]);
        $this->assertNotSame(200, $response->getStatusCode());
    }

    #[Test]
    public function agent_comptable_cannot_access_parent_endpoints(): void
    {
        $token = $this->tokenFor('Agent Comptable');

        $this->withToken($token)
            ->getJson('/api/admin/parent/me')
            ->assertForbidden();
    }
}
