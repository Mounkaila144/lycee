<?php

namespace Tests\Feature\Finance;

use Modules\Enrollment\Entities\Student;
use Modules\Finance\Entities\Invoice;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class PaymentStatisticsApiTest extends TestCase
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

    private function makeStudentWithInvoice(float $total, float $paid): Student
    {
        $student = Student::factory()->create(['class_id' => $this->class->id]);
        if ($total > 0) {
            Invoice::on('tenant')->create([
                'student_id' => $student->id,
                'class_id' => $this->class->id,
                'academic_year_id' => $this->year->id,
                'invoice_number' => 'INV-'.$student->id,
                'invoice_date' => now(),
                'due_date' => now()->addDays(30),
                'total_amount' => $total,
                'paid_amount' => $paid,
                'status' => $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'pending'),
            ]);
        }

        return $student;
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/admin/finance/reports/payment-statistics')->assertStatus(401);
    }

    #[Test]
    public function it_aggregates_paid_partial_unpaid_and_not_billed(): void
    {
        $this->makeStudentWithInvoice(100000, 100000); // paid
        $this->makeStudentWithInvoice(100000, 40000);  // partial
        $this->makeStudentWithInvoice(100000, 0);       // unpaid
        $this->makeStudentWithInvoice(0, 0);            // not billed

        $response = $this->authGetJson('/api/admin/finance/reports/payment-statistics?academic_year_id='.$this->year->id);

        $response->assertOk()
            ->assertJsonPath('data.global.paid', 1)
            ->assertJsonPath('data.global.partial', 1)
            ->assertJsonPath('data.global.unpaid', 1)
            ->assertJsonPath('data.global.not_billed', 1)
            ->assertJsonPath('data.global.amount_billed', 300000)
            ->assertJsonPath('data.global.amount_collected', 140000)
            ->assertJsonPath('data.global.amount_outstanding', 160000);
    }

    #[Test]
    public function it_lists_students_with_an_outstanding_balance(): void
    {
        $paid = $this->makeStudentWithInvoice(100000, 100000);
        $this->makeStudentWithInvoice(100000, 40000);  // partial
        $this->makeStudentWithInvoice(100000, 0);       // unpaid

        $response = $this->authGetJson('/api/admin/finance/reports/outstanding-students?academic_year_id='.$this->year->id);

        $response->assertOk()
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.total_outstanding', 160000);

        $ids = collect($response->json('data.students'))->pluck('student_id');
        $this->assertFalse($ids->contains($paid->id), 'Fully-paid student must be excluded');
    }

    #[Test]
    public function it_filters_outstanding_by_unpaid_status(): void
    {
        $this->makeStudentWithInvoice(100000, 40000);  // partial
        $this->makeStudentWithInvoice(100000, 0);       // unpaid

        $this->authGetJson('/api/admin/finance/reports/outstanding-students?academic_year_id='.$this->year->id.'&status=unpaid')
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.students.0.status', 'unpaid');
    }

    #[Test]
    public function it_breaks_down_by_class(): void
    {
        $this->makeStudentWithInvoice(100000, 100000);

        $response = $this->authGetJson('/api/admin/finance/reports/payment-statistics?academic_year_id='.$this->year->id);

        $response->assertOk();
        $classes = $response->json('data.classes');
        $this->assertNotEmpty($classes);
        $row = collect($classes)->firstWhere('class_id', $this->class->id);
        $this->assertSame(1, $row['paid']);
        $this->assertEqualsWithDelta(100.0, $row['payment_rate'], 0.01);
    }
}
