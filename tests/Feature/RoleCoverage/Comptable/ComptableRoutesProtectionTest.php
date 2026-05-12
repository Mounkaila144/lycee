<?php

namespace Tests\Feature\RoleCoverage\Comptable;

use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Stories Comptable 01-06 — vérification RBAC.
 *
 * Le rôle Comptable est l'opérateur financier le plus large :
 *   ✅ Lecture full Finance (rapports, vue d'ensemble, dashboard) — Story 01, 02
 *   ✅ Refund autorisé — Story 04 (cf. middleware /payments/{id}/refund)
 *   ✅ Exports comptables — Story 05 (FinanceReportController)
 *   ✅ Lecture Paie — Story 06 (Payroll middleware inclut Comptable)
 *   ⚠️ Rapprochement bancaire — Story 03 : tables `bank_accounts` etc. à créer en V2
 *      → out-of-scope test individuel pour cette story (placeholder).
 */
class ComptableRoutesProtectionTest extends TestCase
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
    public function comptable_can_read_finance_invoices(): void
    {
        $token = $this->tokenFor('Comptable');

        $this->withToken($token)
            ->getJson('/api/admin/finance/invoices')
            ->assertOk();
    }

    #[Test]
    public function comptable_can_read_finance_payments(): void
    {
        $token = $this->tokenFor('Comptable');

        $response = $this->withToken($token)->getJson('/api/admin/finance/payments');

        $this->assertNotSame(403, $response->getStatusCode(), 'Comptable doit lire les paiements (Story 02)');
    }

    #[Test]
    public function comptable_can_refund_payment(): void
    {
        $token = $this->tokenFor('Comptable');

        $response = $this->withToken($token)->postJson('/api/admin/finance/payments/1/refund', ['amount' => 100]);

        $this->assertNotSame(403, $response->getStatusCode(), 'Comptable doit pouvoir refund (Story 04)');
    }

    #[Test]
    public function comptable_can_write_off_debt(): void
    {
        $token = $this->tokenFor('Comptable');

        $response = $this->withToken($token)->postJson('/api/admin/finance/collection/write-off/1', []);

        $this->assertNotSame(403, $response->getStatusCode(), 'Comptable doit pouvoir write-off');
    }

    #[Test]
    public function comptable_can_read_payroll(): void
    {
        // Story Comptable 06 — Paie lecture (Payroll middleware inclut Comptable)
        $token = $this->tokenFor('Comptable');

        $response = $this->withToken($token)->getJson('/api/admin/payroll/employees');

        $this->assertNotSame(403, $response->getStatusCode(), 'Comptable doit lire la paie (Story 06)');
    }

    #[Test]
    public function comptable_can_create_invoice(): void
    {
        // Story Comptable 02 : peut créer factures (vue ensemble Finance)
        $token = $this->tokenFor('Comptable');

        $response = $this->withToken($token)->postJson('/api/admin/finance/invoices', []);

        $this->assertNotSame(403, $response->getStatusCode());
    }

    #[Test]
    public function comptable_can_send_reminders(): void
    {
        $token = $this->tokenFor('Comptable');

        $response = $this->withToken($token)->postJson('/api/admin/finance/collection/reminders/send', []);

        $this->assertNotSame(403, $response->getStatusCode());
    }

    #[Test]
    public function comptable_can_record_cashier_close(): void
    {
        $token = $this->tokenFor('Comptable');

        $response = $this->withToken($token)->postJson('/api/admin/finance/cashier-close', [
            'close_date' => now()->toDateString(),
            'total_cash_declared' => 50000,
            'total_cash_system' => 50000,
        ]);

        $this->assertContains($response->getStatusCode(), [201, 422]);
    }

    #[Test]
    public function comptable_cannot_list_users(): void
    {
        $token = $this->tokenFor('Comptable');

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    #[Test]
    public function comptable_cannot_access_teacher_endpoints(): void
    {
        $token = $this->tokenFor('Comptable');

        $this->withToken($token)
            ->getJson('/api/frontend/teacher/my-modules')
            ->assertForbidden();
    }

    #[Test]
    public function comptable_cannot_access_parent_endpoints(): void
    {
        $token = $this->tokenFor('Comptable');

        $this->withToken($token)
            ->getJson('/api/admin/parent/me')
            ->assertForbidden();
    }

    #[Test]
    public function comptable_cannot_create_student(): void
    {
        $token = $this->tokenFor('Comptable');

        $this->withToken($token)
            ->postJson('/api/admin/students', [
                'firstname' => 'Test',
                'lastname' => 'Test',
                'birthdate' => '2010-01-01',
                'sex' => 'M',
            ])
            ->assertForbidden();
    }
}
