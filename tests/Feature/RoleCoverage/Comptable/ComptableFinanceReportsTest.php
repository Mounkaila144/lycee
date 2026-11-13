<?php

namespace Tests\Feature\RoleCoverage\Comptable;

use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Modules\Enrollment\Entities\Student;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Entities\Payment;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Comptable — rapports financiers et tableau de bord trésorerie (Stories 17-23)
 * consommés par la page /admin/finance/reports.
 */
class ComptableFinanceReportsTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $cashier;

    private AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();
        Cache::flush();

        $this->cashier = TenantUser::factory()->create(['application' => 'admin']);
        $this->academicYear = AcademicYear::factory()->create();
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

    private function getAs(string $role, string $uri): TestResponse
    {
        return $this->withToken($this->tokenFor($role))->getJson($uri);
    }

    private function createInvoice(Student $student, array $attributes = []): Invoice
    {
        return Invoice::on('tenant')->create(array_merge([
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
            'invoice_number' => 'INV-TEST-'.uniqid(),
            'invoice_date' => now()->subMonths(2)->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'total_amount' => 100000,
            'paid_amount' => 0,
            'status' => 'pending',
        ], $attributes));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function reportEndpoints(): array
    {
        return [
            'dashboard' => ['/api/admin/finance/reports/dashboard'],
            'payment journal' => ['/api/admin/finance/reports/payment-journal?start_date=2020-01-01&end_date=2030-12-31'],
            'aging balance' => ['/api/admin/finance/reports/aging-balance'],
            'unpaid statements' => ['/api/admin/finance/reports/unpaid-statements'],
            'cash flow forecast' => ['/api/admin/finance/reports/cash-flow-forecast'],
            'collection statistics' => ['/api/admin/finance/reports/collection-statistics'],
            'accounting export' => ['/api/admin/finance/reports/accounting-export?start_date=2020-01-01&end_date=2030-12-31'],
            'summary' => ['/api/admin/finance/reports/summary'],
            'bank accounts' => ['/api/admin/finance/bank-reconciliation/accounts'],
            'bank transactions' => ['/api/admin/finance/bank-reconciliation/transactions'],
            'bank periods' => ['/api/admin/finance/bank-reconciliation/periods'],
        ];
    }

    #[Test]
    #[DataProvider('reportEndpoints')]
    public function comptable_can_read_every_report_shown_in_the_menu(string $uri): void
    {
        $this->getAs('Comptable', $uri)->assertOk()->assertJsonStructure(['data']);
    }

    #[Test]
    public function payment_journal_requires_a_period(): void
    {
        $this->getAs('Comptable', '/api/admin/finance/reports/payment-journal')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['start_date', 'end_date']]);
    }

    #[Test]
    public function payment_journal_does_not_expose_the_full_recorder_account(): void
    {
        $student = Student::factory()->create();
        $invoice = $this->createInvoice($student, ['paid_amount' => 25000, 'status' => 'partial']);
        Payment::on('tenant')->create([
            'invoice_id' => $invoice->id,
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'amount' => 25000,
            'payment_method' => 'cash',
            'receipt_number' => 'REC-TEST-1',
            'recorded_by' => $this->cashier->id,
        ]);

        $response = $this->getAs('Comptable', '/api/admin/finance/reports/payment-journal?start_date='.now()->subDay()->toDateString().'&end_date='.now()->addDay()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.summary.total_count', 1)
            ->assertJsonPath('data.payments.0.invoice.student.matricule', $student->matricule)
            ->assertJsonPath('data.payments.0.recorded_by.username', $this->cashier->username);

        $recordedBy = $response->json('data.payments.0.recorded_by');
        $this->assertEqualsCanonicalizing(['id', 'username', 'firstname', 'lastname'], array_keys($recordedBy));
    }

    #[Test]
    public function aging_balance_classifies_overdue_invoices_with_integer_days(): void
    {
        $student = Student::factory()->create();
        $this->createInvoice($student, ['due_date' => now()->subDays(45)->toDateString()]);
        $this->createInvoice($student, ['due_date' => now()->addDays(10)->toDateString()]);

        $response = $this->getAs('Comptable', '/api/admin/finance/reports/aging-balance')
            ->assertOk()
            ->assertJsonPath('data.aging.31-60.count', 1)
            ->assertJsonPath('data.aging.current.count', 1);

        $this->assertIsInt($response->json('data.aging.31-60.invoices.0.days_overdue'));
        $this->assertEquals(200000, $response->json('data.total_outstanding'));
    }

    #[Test]
    public function overdue_scope_does_not_leak_soft_deleted_or_other_year_invoices(): void
    {
        $student = Student::factory()->create();
        $otherYear = AcademicYear::factory()->create();

        $this->createInvoice($student, ['due_date' => now()->subDays(5)->toDateString()]);
        $this->createInvoice($student, ['status' => 'overdue', 'academic_year_id' => $otherYear->id]);
        $this->createInvoice($student, ['due_date' => now()->subDays(5)->toDateString()])->delete();

        $this->assertSame(2, Invoice::on('tenant')->overdue()->count());
        $this->assertSame(
            1,
            Invoice::on('tenant')->overdue()->where('academic_year_id', $this->academicYear->id)->count()
        );
    }

    #[Test]
    public function treasury_dashboard_overdue_total_respects_academic_year_filter(): void
    {
        $student = Student::factory()->create();
        $otherYear = AcademicYear::factory()->create();

        $this->createInvoice($student, ['due_date' => now()->subDays(5)->toDateString(), 'total_amount' => 30000]);
        $this->createInvoice($student, ['status' => 'overdue', 'academic_year_id' => $otherYear->id, 'total_amount' => 70000]);

        $response = $this->getAs('Comptable', '/api/admin/finance/reports/dashboard?academic_year_id='.$this->academicYear->id)
            ->assertOk();

        $this->assertEquals(30000, $response->json('data.summary.total_overdue'));
        $this->assertEquals(30000, $response->json('data.summary.total_invoiced'));
    }

    #[Test]
    public function cash_flow_forecast_months_are_labelled_in_french(): void
    {
        $expectedMonth = now()->startOfMonth()->locale('fr')->translatedFormat('F Y');

        $this->getAs('Comptable', '/api/admin/finance/reports/cash-flow-forecast?months=3')
            ->assertOk()
            ->assertJsonCount(3, 'data.forecast')
            ->assertJsonPath('data.forecast.0.month', $expectedMonth);
    }

    #[Test]
    public function caissier_and_manager_cannot_read_bank_reconciliation(): void
    {
        $this->getAs('Caissier', '/api/admin/finance/bank-reconciliation/accounts')->assertForbidden();
        $this->getAs('Manager', '/api/admin/finance/bank-reconciliation/accounts')->assertForbidden();
    }

    #[Test]
    public function student_cannot_read_finance_reports(): void
    {
        $this->getAs('Élève', '/api/admin/finance/reports/dashboard')->assertForbidden();
    }
}
