<?php

namespace Tests\Feature\RoleCoverage\Comptable;

use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Story Comptable 03 — Rapprochement bancaire.
 *
 * 4 tables créées (bank_accounts, bank_transactions, payment_bank_transaction_matches,
 * reconciliation_periods). Endpoints Comptable/Administrator uniquement.
 */
class BankReconciliationTest extends TestCase
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
    public function comptable_can_list_bank_accounts(): void
    {
        $token = $this->tokenFor('Comptable');

        $this->withToken($token)
            ->getJson('/api/admin/finance/bank-reconciliation/accounts')
            ->assertOk();
    }

    #[Test]
    public function comptable_can_create_bank_account(): void
    {
        $token = $this->tokenFor('Comptable');

        $this->withToken($token)
            ->postJson('/api/admin/finance/bank-reconciliation/accounts', [
                'name' => 'Compte Principal',
                'iban' => 'NE12345678901234567890',
                'bank_name' => 'BOA Niger',
                'currency' => 'XOF',
                'opening_balance' => 1000000,
            ])
            ->assertStatus(201);
    }

    #[Test]
    public function comptable_can_list_transactions(): void
    {
        $token = $this->tokenFor('Comptable');

        $this->withToken($token)
            ->getJson('/api/admin/finance/bank-reconciliation/transactions')
            ->assertOk();
    }

    #[Test]
    public function comptable_can_list_periods(): void
    {
        $token = $this->tokenFor('Comptable');

        $this->withToken($token)
            ->getJson('/api/admin/finance/bank-reconciliation/periods')
            ->assertOk();
    }

    #[Test]
    public function administrator_can_access_bank_reconciliation(): void
    {
        $token = $this->tokenFor('Administrator');

        $this->withToken($token)
            ->getJson('/api/admin/finance/bank-reconciliation/accounts')
            ->assertOk();
    }

    #[Test]
    public function caissier_cannot_access_bank_reconciliation(): void
    {
        $token = $this->tokenFor('Caissier');

        $this->withToken($token)
            ->getJson('/api/admin/finance/bank-reconciliation/accounts')
            ->assertForbidden();
    }

    #[Test]
    public function agent_comptable_cannot_access_bank_reconciliation(): void
    {
        $token = $this->tokenFor('Agent Comptable');

        $this->withToken($token)
            ->getJson('/api/admin/finance/bank-reconciliation/accounts')
            ->assertForbidden();
    }
}
