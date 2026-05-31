<?php

namespace Tests\Feature\Finance;

use Modules\Enrollment\Entities\Student;
use Modules\Finance\Entities\ClassFee;
use Modules\Finance\Entities\Invoice;
use Modules\Finance\Services\BillingService;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ClassFeeApiTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $user;

    private string $token;

    private AcademicYear $year;

    private Classe $class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($this->user, 'Administrator');
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $this->year = AcademicYear::factory()->create(['is_active' => true]);
        $level = Level::factory()->create();
        $this->class = Classe::factory()->create(['academic_year_id' => $this->year->id, 'level_id' => $level->id]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function authGetJson(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->getJson($uri);
    }

    private function authPostJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->postJson($uri, $data);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/admin/finance/class-fees?class_id='.$this->class->id)->assertStatus(401);
    }

    #[Test]
    public function it_syncs_a_class_fee_schedule(): void
    {
        $response = $this->authPostJson('/api/admin/finance/class-fees/sync', [
            'class_id' => $this->class->id,
            'lines' => [
                ['fee_type_id' => null, 'label' => 'Scolarité + Inscription', 'amount' => 265000],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.total', 265000);

        $this->assertSame(1, ClassFee::on('tenant')->where('class_id', $this->class->id)->count());
    }

    #[Test]
    public function it_replaces_lines_on_resync(): void
    {
        ClassFee::on('tenant')->create(['class_id' => $this->class->id, 'label' => 'Old', 'amount' => 100]);

        $this->authPostJson('/api/admin/finance/class-fees/sync', [
            'class_id' => $this->class->id,
            'lines' => [['fee_type_id' => null, 'label' => 'New', 'amount' => 200]],
        ])->assertOk();

        $this->assertSame(1, ClassFee::on('tenant')->where('class_id', $this->class->id)->count());
        $this->assertSame('New', ClassFee::on('tenant')->where('class_id', $this->class->id)->value('label'));
    }

    #[Test]
    public function it_auto_generates_an_invoice_from_the_class_fee_schedule(): void
    {
        ClassFee::on('tenant')->create(['class_id' => $this->class->id, 'label' => 'Scolarité + Inscription', 'amount' => 265000]);

        $student = Student::factory()->create(['class_id' => $this->class->id]);

        $invoice = app(BillingService::class)->generateClassInvoice($student);

        $this->assertNotNull($invoice);
        $this->assertEqualsWithDelta(265000, (float) $invoice->total_amount, 0.01);
        $this->assertSame($this->class->id, $invoice->class_id);
        $this->assertSame($this->year->id, $invoice->academic_year_id);
    }

    #[Test]
    public function it_is_idempotent_and_does_not_duplicate_invoices(): void
    {
        ClassFee::on('tenant')->create(['class_id' => $this->class->id, 'label' => 'Frais', 'amount' => 100000]);
        $student = Student::factory()->create(['class_id' => $this->class->id]);

        $svc = app(BillingService::class);
        $first = $svc->generateClassInvoice($student);
        $second = $svc->generateClassInvoice($student);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Invoice::on('tenant')->where('student_id', $student->id)->count());
    }

    #[Test]
    public function it_returns_null_when_the_class_has_no_fee_schedule(): void
    {
        $student = Student::factory()->create(['class_id' => $this->class->id]);

        $this->assertNull(app(BillingService::class)->generateClassInvoice($student));
    }
}
