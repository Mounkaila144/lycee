<?php

namespace Tests\Feature\RoleCoverage\Caissier;

use Modules\Enrollment\Entities\Student;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Entities\Payment;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Caissier — écrans Facturation / Paiements.
 *
 * Couvre :
 *   - lecture factures + paiements (recherche matricule, tri whitelisté)
 *   - encaissement d'une facture par le Caissier + reçu
 *   - aucune fuite de champs sensibles de l'utilisateur ayant encaissé
 *   - actions interdites : créer une facture, paramétrer les types de frais
 */
class CaissierBillingTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $cashier;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->cashier = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($this->cashier, 'Caissier');
        $this->token = $this->cashier->createToken('test-token')->plainTextToken;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function invoiceFor(Student $student, float $total = 100000): Invoice
    {
        return Invoice::on('tenant')->create([
            'student_id' => $student->id,
            'academic_year_id' => (AcademicYear::query()->first() ?? AcademicYear::factory()->create())->id,
            'invoice_number' => 'INV-CAISSE-'.uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'total_amount' => $total,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function caissier_records_payment_and_gets_receipt(): void
    {
        $invoice = $this->invoiceFor(Student::factory()->create());

        $response = $this->withToken($this->token)->postJson('/api/admin/finance/payments', [
            'invoice_id' => $invoice->id,
            'amount' => 40000,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201);
        $paymentId = $response->json('data.id');

        $this->assertEquals(40000, (float) $invoice->fresh()->paid_amount);
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame($this->cashier->id, Payment::on('tenant')->find($paymentId)->recorded_by);

        $this->withToken($this->token)
            ->getJson("/api/admin/finance/payments/{$paymentId}/receipt")
            ->assertOk()
            ->assertJsonPath('data.invoice_number', $invoice->invoice_number)
            ->assertJsonPath('data.recorded_by', trim($this->cashier->firstname.' '.$this->cashier->lastname));
    }

    #[Test]
    public function caissier_cannot_overpay_an_invoice(): void
    {
        $invoice = $this->invoiceFor(Student::factory()->create(), 10000);

        $this->withToken($this->token)->postJson('/api/admin/finance/payments', [
            'invoice_id' => $invoice->id,
            'amount' => 20000,
            'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->assertEquals(0, (float) $invoice->fresh()->paid_amount);
    }

    #[Test]
    public function payment_listing_does_not_leak_recorder_secrets(): void
    {
        $invoice = $this->invoiceFor(Student::factory()->create());
        $this->withToken($this->token)->postJson('/api/admin/finance/payments', [
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'payment_method' => 'cash',
        ])->assertStatus(201);

        $response = $this->withToken($this->token)->getJson('/api/admin/finance/payments');

        $response->assertOk()
            ->assertJsonPath('data.0.recorded_by.id', $this->cashier->id)
            ->assertJsonMissingPath('data.0.recorded_by.remember_token')
            ->assertJsonMissingPath('data.0.recorded_by.email');
    }

    #[Test]
    public function payments_and_invoices_can_be_searched_by_student_matricule(): void
    {
        $target = Student::factory()->create(['matricule' => 'MAT-CAISSE-001']);
        $other = Student::factory()->create(['matricule' => 'MAT-AUTRE-002']);
        $targetInvoice = $this->invoiceFor($target);
        $otherInvoice = $this->invoiceFor($other);

        foreach ([$targetInvoice, $otherInvoice] as $invoice) {
            $this->withToken($this->token)->postJson('/api/admin/finance/payments', [
                'invoice_id' => $invoice->id,
                'amount' => 500,
                'payment_method' => 'cash',
            ])->assertStatus(201);
        }

        $this->withToken($this->token)
            ->getJson('/api/admin/finance/payments?search=MAT-CAISSE')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_id', $targetInvoice->id);

        $this->withToken($this->token)
            ->getJson('/api/admin/finance/invoices?search=MAT-CAISSE')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $targetInvoice->id);
    }

    #[Test]
    public function unknown_sort_column_falls_back_instead_of_failing(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/admin/finance/payments?sort_by=password&sort_order=sideways')
            ->assertOk();

        $this->withToken($this->token)
            ->getJson('/api/admin/finance/invoices?sort_by=unknown_column')
            ->assertOk();
    }

    #[Test]
    public function caissier_can_read_daily_summary_and_fee_types(): void
    {
        $this->withToken($this->token)->getJson('/api/admin/finance/payments/summary/daily')->assertOk();
        $this->withToken($this->token)->getJson('/api/admin/finance/fee-types')->assertOk();
        $this->withToken($this->token)->getJson('/api/admin/finance/cashier-close/preview')->assertOk();
    }

    #[Test]
    public function caissier_cannot_create_invoice(): void
    {
        $student = Student::factory()->create();

        $this->withToken($this->token)->postJson('/api/admin/finance/invoices', [
            'student_id' => $student->id,
            'academic_year_id' => (AcademicYear::query()->first() ?? AcademicYear::factory()->create())->id,
            'items' => [['description' => 'Frais', 'quantity' => 1, 'unit_price' => 1000]],
        ])->assertForbidden();
    }

    #[Test]
    public function caissier_cannot_manage_fee_types(): void
    {
        $this->withToken($this->token)->postJson('/api/admin/finance/fee-types', [
            'code' => 'TEST',
            'name' => 'Test',
        ])->assertForbidden();

        $this->withToken($this->token)->putJson('/api/admin/finance/fee-types/1', [
            'name' => 'Test',
        ])->assertForbidden();
    }
}
