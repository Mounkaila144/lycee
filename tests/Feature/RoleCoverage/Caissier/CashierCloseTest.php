<?php

namespace Tests\Feature\RoleCoverage\Caissier;

use Modules\Enrollment\Entities\Student;
use Modules\Finance\Entities\CashierCloseRecord;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Entities\Payment;
use Modules\StructureAcademique\Entities\AcademicYear;
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

    /**
     * Crée un encaissement enregistré par l'utilisateur donné.
     */
    private function recordPaymentBy(TenantUser $user, float $amount, string $method = 'cash', ?string $date = null): Payment
    {
        $student = Student::factory()->create();
        $invoice = Invoice::on('tenant')->create([
            'student_id' => $student->id,
            'academic_year_id' => (AcademicYear::query()->first() ?? AcademicYear::factory()->create())->id,
            'invoice_number' => 'INV-CLOSE-'.uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'total_amount' => $amount,
            'paid_amount' => $amount,
            'status' => 'paid',
        ]);

        return Payment::on('tenant')->create([
            'invoice_id' => $invoice->id,
            'student_id' => $student->id,
            'payment_date' => $date ?? now()->toDateString(),
            'amount' => $amount,
            'payment_method' => $method,
            'receipt_number' => 'REC-'.uniqid(),
            'recorded_by' => $user->id,
        ]);
    }

    #[Test]
    public function caissier_can_record_cashier_close(): void
    {
        [$cashier, $token] = $this->tokenFor('Caissier');
        $this->recordPaymentBy($cashier, 100000);
        $this->recordPaymentBy($cashier, 50000);
        $this->recordPaymentBy($cashier, 25000, 'mobile_money');

        $response = $this->withToken($token)->postJson('/api/admin/finance/cashier-close', [
            'close_date' => now()->toDateString(),
            'total_cash_declared' => 150000,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.total_cash_system', '150000.00')
            ->assertJsonPath('data.total_mobile_money', '25000.00')
            ->assertJsonPath('data.variance', '0.00');
    }

    #[Test]
    public function cashier_close_records_variance_when_amounts_differ(): void
    {
        [$cashier, $token] = $this->tokenFor('Caissier');
        $this->recordPaymentBy($cashier, 150000);

        $response = $this->withToken($token)->postJson('/api/admin/finance/cashier-close', [
            'close_date' => now()->toDateString(),
            'total_cash_declared' => 149500,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'variance_pending')
            ->assertJsonPath('data.variance', '-500.00');
    }

    #[Test]
    public function system_total_ignores_client_value_and_other_cashiers_payments(): void
    {
        [$cashier, $token] = $this->tokenFor('Caissier');
        [$otherCashier] = $this->tokenFor('Caissier');
        $this->recordPaymentBy($cashier, 40000);
        $this->recordPaymentBy($otherCashier, 90000);
        $this->recordPaymentBy($cashier, 70000, 'cash', now()->subDay()->toDateString());

        $this->withToken($token)->postJson('/api/admin/finance/cashier-close', [
            'close_date' => now()->toDateString(),
            'total_cash_declared' => 40000,
            'total_cash_system' => 999999,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.total_cash_system', '40000.00')
            ->assertJsonPath('data.status', 'closed');
    }

    #[Test]
    public function preview_returns_system_totals_for_connected_cashier(): void
    {
        [$cashier, $token] = $this->tokenFor('Caissier');
        $this->recordPaymentBy($cashier, 30000);
        $this->recordPaymentBy($cashier, 12000, 'check');

        $this->withToken($token)
            ->getJson('/api/admin/finance/cashier-close/preview?date='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.total_cash_system', 30000)
            ->assertJsonPath('data.total_cheque', 12000)
            ->assertJsonPath('data.payments_count', 2)
            ->assertJsonPath('data.already_closed', false);
    }

    #[Test]
    public function cashier_cannot_close_same_day_twice(): void
    {
        [, $token] = $this->tokenFor('Caissier');
        $payload = ['close_date' => now()->toDateString(), 'total_cash_declared' => 0];

        $this->withToken($token)->postJson('/api/admin/finance/cashier-close', $payload)->assertStatus(201);
        $this->withToken($token)->postJson('/api/admin/finance/cashier-close', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('close_date');

        $this->assertSame(1, CashierCloseRecord::query()->count());
    }

    #[Test]
    public function cashier_cannot_close_a_future_date(): void
    {
        [, $token] = $this->tokenFor('Caissier');

        $this->withToken($token)->postJson('/api/admin/finance/cashier-close', [
            'close_date' => now()->addDay()->toDateString(),
            'total_cash_declared' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('close_date');
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
