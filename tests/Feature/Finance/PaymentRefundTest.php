<?php

namespace Tests\Feature\Finance;

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
 * Remboursement d'un paiement : un même paiement ne peut pas être remboursé
 * au-delà de son montant (cumul des remboursements), ni un remboursement remboursé.
 */
class PaymentRefundTest extends TestCase
{
    use InteractsWithTenancy;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Comptable');
        $this->token = $user->createToken('test-token')->plainTextToken;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * @return array{0: Invoice, 1: Payment}
     */
    private function invoiceWithPayments(float $otherPayment, float $paymentAmount): array
    {
        $student = Student::factory()->create();
        $invoice = Invoice::on('tenant')->create([
            'student_id' => $student->id,
            'academic_year_id' => (AcademicYear::query()->first() ?? AcademicYear::factory()->create())->id,
            'invoice_number' => 'INV-REFUND-'.uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'total_amount' => 72500,
            'paid_amount' => $otherPayment + $paymentAmount,
            'status' => 'partial',
        ]);

        Payment::on('tenant')->create([
            'invoice_id' => $invoice->id,
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'amount' => $otherPayment,
            'payment_method' => 'cash',
            'receipt_number' => 'REC-OTHER-'.uniqid(),
        ]);

        $payment = Payment::on('tenant')->create([
            'invoice_id' => $invoice->id,
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'amount' => $paymentAmount,
            'payment_method' => 'cash',
            'receipt_number' => 'REC-E2E-'.uniqid(),
        ]);

        return [$invoice, $payment];
    }

    private function refund(int $paymentId, float $amount): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->postJson("/api/admin/finance/payments/{$paymentId}/refund", [
            'amount' => $amount,
            'reason' => 'Test',
        ]);
    }

    #[Test]
    public function a_payment_can_be_fully_refunded_once(): void
    {
        [$invoice, $payment] = $this->invoiceWithPayments(30000, 1000);

        $this->refund($payment->id, 1000)->assertCreated();

        $this->assertEquals(30000, (float) $invoice->fresh()->paid_amount);
    }

    #[Test]
    public function a_fully_refunded_payment_cannot_be_refunded_again(): void
    {
        [$invoice, $payment] = $this->invoiceWithPayments(30000, 1000);

        $this->refund($payment->id, 1000)->assertCreated();
        $this->refund($payment->id, 1000)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ce paiement a déjà été entièrement remboursé');

        $this->assertEquals(30000, (float) $invoice->fresh()->paid_amount);
    }

    #[Test]
    public function partial_refunds_cannot_exceed_the_payment_amount(): void
    {
        [$invoice, $payment] = $this->invoiceWithPayments(30000, 1000);

        $this->refund($payment->id, 600)->assertCreated();
        $this->refund($payment->id, 500)->assertStatus(422);
        $this->refund($payment->id, 400)->assertCreated();

        $this->assertEquals(30000, (float) $invoice->fresh()->paid_amount);
    }

    #[Test]
    public function a_refund_line_cannot_itself_be_refunded(): void
    {
        [, $payment] = $this->invoiceWithPayments(30000, 1000);

        $refundId = $this->refund($payment->id, 1000)->assertCreated()->json('data.id');

        $this->refund($refundId, 1000)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Un remboursement ne peut pas être remboursé');
    }
}
