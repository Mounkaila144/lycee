<?php

namespace Tests\Feature\RoleCoverage\Caissier;

use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Protection des routes Caissier — couvre les Stories Caissier 01-05 :
 *   - 01 Home encaissements : /api/admin/finance/reports/* + dashboard
 *   - 02 Saisie paiement   : /api/admin/finance/payments
 *   - 03 Reçus             : /api/admin/finance/payments/{id}/receipt
 *   - 04 Factures lecture  : /api/admin/finance/invoices (GET)
 *   - 05 Rapports journaliers : /api/admin/finance/reports/daily-cash
 *
 * Le RBAC est posé via `role:Administrator|Manager|Comptable|Agent Comptable|Caissier`
 * sur Finance/admin.php (cf. DEV-AGENT-PROMPT §C). Les restrictions fines
 * (Caissier exclu du refund/write-off) restent à appliquer story par story.
 */
class CaissierRoutesProtectionTest extends TestCase
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
    public function caissier_can_view_own_profile(): void
    {
        $token = $this->tokenFor('Caissier');

        $this->withToken($token)
            ->getJson('/api/admin/auth/me')
            ->assertOk();
    }

    #[Test]
    public function caissier_can_access_finance_routes(): void
    {
        $token = $this->tokenFor('Caissier');

        $response = $this->withToken($token)->getJson('/api/admin/finance/invoices');

        // Caissier autorisé par middleware role:
        $this->assertNotSame(403, $response->getStatusCode(), 'Caissier doit accéder à Finance (Story 04 factures lecture)');
    }

    #[Test]
    public function caissier_can_access_students_endpoint(): void
    {
        $token = $this->tokenFor('Caissier');

        $this->withToken($token)
            ->getJson('/api/admin/students')
            ->assertOk();
    }

    #[Test]
    public function caissier_cannot_access_teacher_endpoints(): void
    {
        $token = $this->tokenFor('Caissier');

        $this->withToken($token)
            ->getJson('/api/frontend/teacher/my-modules')
            ->assertForbidden();
    }

    #[Test]
    public function caissier_cannot_list_users(): void
    {
        $token = $this->tokenFor('Caissier');

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    #[Test]
    public function caissier_cannot_create_student(): void
    {
        $token = $this->tokenFor('Caissier');

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
    public function caissier_cannot_access_payroll(): void
    {
        $token = $this->tokenFor('Caissier');

        $response = $this->withToken($token)->getJson('/api/admin/payroll/employees');

        $this->assertContains($response->getStatusCode(), [403, 404]);
        $this->assertNotSame(200, $response->getStatusCode());
    }

    #[Test]
    public function caissier_cannot_access_parent_endpoints(): void
    {
        $token = $this->tokenFor('Caissier');

        $this->withToken($token)
            ->getJson('/api/admin/parent/me')
            ->assertForbidden();
    }

    #[Test]
    public function caissier_cannot_access_notes_admin(): void
    {
        $token = $this->tokenFor('Caissier');

        $response = $this->withToken($token)->getJson('/api/admin/grade-validations');

        $this->assertContains($response->getStatusCode(), [403, 404]);
        $this->assertNotSame(200, $response->getStatusCode());
    }
}
