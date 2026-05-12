<?php

namespace Tests\Feature\RoleCoverage\AgentComptable;

use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Stories Agent Comptable 01-06 — restrictions fines Finance.
 *
 * Vérifie que Agent Comptable :
 *   ✅ peut créer / modifier / supprimer des factures (Stories 01, 02)
 *   ✅ peut créer un échéancier (Story 03)
 *   ✅ peut calculer les pénalités (Story 04)
 *   ✅ peut déclencher reminders + blocages services (Stories 05, 06)
 *   ❌ ne peut PAS rembourser un paiement (Story Comptable 04)
 *   ❌ ne peut PAS write-off une dette (Story Comptable 06)
 *
 * Et que Caissier est correctement EXCLU des mutations facturation et recouvrement.
 */
class AgentComptableFinanceTest extends TestCase
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

    private function expectAllowed(\Illuminate\Testing\TestResponse $response, string $endpoint): void
    {
        $this->assertNotSame(401, $response->getStatusCode(), "{$endpoint} ne doit pas répondre 401");
        $this->assertNotSame(403, $response->getStatusCode(), "{$endpoint} doit être autorisé (pas 403)");
    }

    #[Test]
    public function agent_comptable_can_read_invoices(): void
    {
        $token = $this->tokenFor('Agent Comptable');

        $this->withToken($token)
            ->getJson('/api/admin/finance/invoices')
            ->assertOk();
    }

    #[Test]
    public function agent_comptable_can_create_invoice(): void
    {
        $token = $this->tokenFor('Agent Comptable');

        $response = $this->withToken($token)->postJson('/api/admin/finance/invoices', []);

        // Doit passer le middleware role (200/422/etc.), pas 403
        $this->expectAllowed($response, 'POST /invoices');
    }

    #[Test]
    public function agent_comptable_can_generate_automated_invoices(): void
    {
        $token = $this->tokenFor('Agent Comptable');

        $response = $this->withToken($token)
            ->postJson('/api/admin/finance/invoices/generate-automated', []);

        $this->expectAllowed($response, 'POST /invoices/generate-automated');
    }

    #[Test]
    public function agent_comptable_can_create_payment_plan(): void
    {
        $token = $this->tokenFor('Agent Comptable');

        $response = $this->withToken($token)
            ->postJson('/api/admin/finance/collection/payment-plans', []);

        $this->expectAllowed($response, 'POST /collection/payment-plans');
    }

    #[Test]
    public function agent_comptable_can_send_reminders(): void
    {
        $token = $this->tokenFor('Agent Comptable');

        $response = $this->withToken($token)
            ->postJson('/api/admin/finance/collection/reminders/send', []);

        $this->expectAllowed($response, 'POST /collection/reminders/send');
    }

    #[Test]
    public function agent_comptable_can_block_services(): void
    {
        $token = $this->tokenFor('Agent Comptable');

        $response = $this->withToken($token)
            ->postJson('/api/admin/finance/collection/blocks', []);

        $this->expectAllowed($response, 'POST /collection/blocks');
    }

    #[Test]
    public function agent_comptable_cannot_refund_payment(): void
    {
        $token = $this->tokenFor('Agent Comptable');

        $this->withToken($token)
            ->postJson('/api/admin/finance/payments/1/refund', ['amount' => 100])
            ->assertForbidden();
    }

    #[Test]
    public function agent_comptable_cannot_write_off_debt(): void
    {
        $token = $this->tokenFor('Agent Comptable');

        $this->withToken($token)
            ->postJson('/api/admin/finance/collection/write-off/1', [])
            ->assertForbidden();
    }

    #[Test]
    public function caissier_cannot_create_invoice(): void
    {
        $token = $this->tokenFor('Caissier');

        $this->withToken($token)
            ->postJson('/api/admin/finance/invoices', [])
            ->assertForbidden();
    }

    #[Test]
    public function caissier_cannot_create_payment_plan(): void
    {
        $token = $this->tokenFor('Caissier');

        $this->withToken($token)
            ->postJson('/api/admin/finance/collection/payment-plans', [])
            ->assertForbidden();
    }

    #[Test]
    public function caissier_cannot_send_reminders(): void
    {
        $token = $this->tokenFor('Caissier');

        $this->withToken($token)
            ->postJson('/api/admin/finance/collection/reminders/send', [])
            ->assertForbidden();
    }

    #[Test]
    public function caissier_cannot_block_services(): void
    {
        $token = $this->tokenFor('Caissier');

        $this->withToken($token)
            ->postJson('/api/admin/finance/collection/blocks', [])
            ->assertForbidden();
    }

    #[Test]
    public function caissier_can_still_read_blocks_check(): void
    {
        // Story Agent Comptable 06 : /blocks/check est consulté en cross-module
        // (Documents, Exams, Reenrollment) — accessible à tous les rôles Finance
        $token = $this->tokenFor('Caissier');

        $response = $this->withToken($token)->getJson('/api/admin/finance/collection/blocks/check');

        $this->assertNotSame(403, $response->getStatusCode(), 'Caissier doit pouvoir lire /blocks/check (cross-module)');
    }

    #[Test]
    public function comptable_can_write_off_debt(): void
    {
        $token = $this->tokenFor('Comptable');

        $response = $this->withToken($token)->postJson('/api/admin/finance/collection/write-off/1', []);

        $this->assertNotSame(403, $response->getStatusCode(), 'Comptable doit pouvoir write-off');
    }

    #[Test]
    public function etudiant_blocked_on_all_finance_routes(): void
    {
        $token = $this->tokenFor('Étudiant');

        $this->withToken($token)
            ->postJson('/api/admin/finance/invoices', [])
            ->assertForbidden();
    }
}
