<?php

namespace Tests\Feature\RoleCoverage\Caissier;

use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Story Caissier 05 — Clôture journalière de caisse.
 *
 * Couvre :
 *   - POST /api/admin/finance/cashier-close : créer une clôture
 *   - GET  /api/admin/finance/cashier-close : lister mes clôtures (filtre owner Caissier)
 *   - Variance détectée → status 'variance_pending'
 *   - Pas de variance → status 'closed'
 *   - Admin voit toutes les clôtures (pour rapprochement)
 *   - Cross-rôle : Étudiant/Parent bloqués
 */
class CashierCloseTest extends TestCase
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

    private function tokenFor(string $role): array
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, $role);

        return [$user, $user->createToken('test-token')->plainTextToken];
    }

    #[Test]
    public function caissier_can_record_cashier_close(): void
    {
        [, $token] = $this->tokenFor('Caissier');

        $response = $this->withToken($token)->postJson('/api/admin/finance/cashier-close', [
            'close_date' => now()->toDateString(),
            'total_cash_declared' => 150000,
            'total_cash_system' => 150000,
            'total_cheque' => 0,
            'total_mobile_money' => 25000,
            'total_card' => 0,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.variance', '0.00');
    }

    #[Test]
    public function cashier_close_records_variance_when_amounts_differ(): void
    {
        [, $token] = $this->tokenFor('Caissier');

        $response = $this->withToken($token)->postJson('/api/admin/finance/cashier-close', [
            'close_date' => now()->toDateString(),
            'total_cash_declared' => 149500,
            'total_cash_system' => 150000,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'variance_pending')
            ->assertJsonPath('data.variance', '-500.00');
    }

    #[Test]
    public function caissier_only_sees_own_closes(): void
    {
        [, $tokenA] = $this->tokenFor('Caissier');
        [, $tokenB] = $this->tokenFor('Caissier');

        $this->withToken($tokenA)->postJson('/api/admin/finance/cashier-close', [
            'close_date' => '2026-05-10',
            'total_cash_declared' => 100000,
            'total_cash_system' => 100000,
        ])->assertStatus(201);

        $this->withToken($tokenB)->postJson('/api/admin/finance/cashier-close', [
            'close_date' => '2026-05-11',
            'total_cash_declared' => 80000,
            'total_cash_system' => 80000,
        ])->assertStatus(201);

        // Caissier A ne voit que sa clôture
        $responseA = $this->withToken($tokenA)->getJson('/api/admin/finance/cashier-close');
        $responseA->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function administrator_sees_all_cashier_closes(): void
    {
        [, $cashierToken] = $this->tokenFor('Caissier');
        [, $adminToken] = $this->tokenFor('Administrator');

        $this->withToken($cashierToken)->postJson('/api/admin/finance/cashier-close', [
            'close_date' => now()->toDateString(),
            'total_cash_declared' => 50000,
            'total_cash_system' => 50000,
        ])->assertStatus(201);

        $this->withToken($adminToken)
            ->getJson('/api/admin/finance/cashier-close')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function caissier_cannot_refund_payment(): void
    {
        [, $token] = $this->tokenFor('Caissier');

        // Tentative de refund — doit être bloqué par le nouveau middleware fin
        $this->withToken($token)
            ->postJson('/api/admin/finance/payments/1/refund', ['amount' => 100])
            ->assertForbidden();
    }

    #[Test]
    public function caissier_cannot_apply_discount(): void
    {
        [, $token] = $this->tokenFor('Caissier');

        $this->withToken($token)
            ->postJson('/api/admin/finance/discounts', [
                'invoice_id' => 1,
                'amount' => 100,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function etudiant_cannot_access_cashier_close(): void
    {
        [, $token] = $this->tokenFor('Étudiant');

        $this->withToken($token)
            ->getJson('/api/admin/finance/cashier-close')
            ->assertForbidden();
    }

    #[Test]
    public function parent_cannot_access_cashier_close(): void
    {
        [, $token] = $this->tokenFor('Parent');

        $this->withToken($token)
            ->getJson('/api/admin/finance/cashier-close')
            ->assertForbidden();
    }
}
