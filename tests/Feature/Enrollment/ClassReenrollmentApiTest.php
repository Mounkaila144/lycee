<?php

namespace Tests\Feature\Enrollment;

use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentClassEnrollment;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ClassReenrollmentApiTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $user;

    private string $token;

    private AcademicYear $year1;

    private AcademicYear $year2;

    private Level $level6;

    private Classe $source;

    private Student $a;

    private Student $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($this->user, 'Administrator');
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $this->year1 = AcademicYear::factory()->create(['name' => '2026-2027']);
        $this->year2 = AcademicYear::factory()->create(['name' => '2027-2028']);
        $this->level6 = Level::factory()->create(['code' => '6E', 'name' => 'Sixième', 'order_index' => 1]);
        Level::factory()->create(['code' => '5E', 'name' => 'Cinquième', 'order_index' => 2]);

        $this->source = Classe::factory()->create([
            'academic_year_id' => $this->year1->id,
            'level_id' => $this->level6->id,
            'series_id' => null,
            'section' => 'A',
        ]);

        $this->a = Student::factory()->create(['class_id' => $this->source->id, 'matricule' => 'STU-A', 'status' => 'Actif']);
        $this->b = Student::factory()->create(['class_id' => $this->source->id, 'matricule' => 'STU-B', 'status' => 'Actif']);
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
        $this->getJson('/api/admin/enrollment/reenrollment/preview?class_id='.$this->source->id.'&target_academic_year_id='.$this->year2->id)
            ->assertStatus(401);
    }

    #[Test]
    public function it_previews_the_next_level_and_students(): void
    {
        $response = $this->authGetJson('/api/admin/enrollment/reenrollment/preview?class_id='.$this->source->id.'&target_academic_year_id='.$this->year2->id);

        $response->assertOk()
            ->assertJsonPath('data.next_level.name', 'Cinquième')
            ->assertJsonPath('data.is_terminal', false);
        $this->assertCount(2, $response->json('data.students'));
    }

    #[Test]
    public function it_promotes_and_repeats_students_and_writes_history(): void
    {
        $response = $this->authPostJson('/api/admin/enrollment/reenrollment/process', [
            'source_class_id' => $this->source->id,
            'target_academic_year_id' => $this->year2->id,
            'decisions' => [
                ['student_id' => $this->a->id, 'action' => 'promote'],
                ['student_id' => $this->b->id, 'action' => 'repeat'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.promoted', 1)
            ->assertJsonPath('data.repeated', 1);

        // History rows for the target year.
        $this->assertSame(2, StudentClassEnrollment::on('tenant')->where('academic_year_id', $this->year2->id)->count());

        // Promoted student moved to a 5e class; repeated stayed in 6e — both for year 2.
        $promoted = Student::on('tenant')->find($this->a->id);
        $promotedClass = Classe::on('tenant')->find($promoted->class_id);
        $this->assertSame('5E', $promotedClass->level->code);
        $this->assertSame($this->year2->id, $promotedClass->academic_year_id);

        $repeated = Student::on('tenant')->find($this->b->id);
        $repeatedClass = Classe::on('tenant')->find($repeated->class_id);
        $this->assertSame('6E', $repeatedClass->level->code);
        $this->assertSame($this->year2->id, $repeatedClass->academic_year_id);
    }

    #[Test]
    public function it_returns_the_roster_for_a_year(): void
    {
        $this->authPostJson('/api/admin/enrollment/reenrollment/process', [
            'source_class_id' => $this->source->id,
            'target_academic_year_id' => $this->year2->id,
            'decisions' => [['student_id' => $this->a->id, 'action' => 'promote']],
        ])->assertOk();

        $this->authGetJson('/api/admin/enrollment/reenrollment/roster?academic_year_id='.$this->year2->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.enrollment_type', 'promotion');
    }

    #[Test]
    public function it_reenrolls_a_single_student_to_the_next_level(): void
    {
        $response = $this->authPostJson('/api/admin/enrollment/reenrollment/student', [
            'student_id' => $this->a->id,
            'target_academic_year_id' => $this->year2->id,
            'action' => 'promote',
        ]);

        $response->assertOk()->assertJsonPath('data.enrollment_type', 'promotion');

        $student = Student::on('tenant')->find($this->a->id);
        $class = Classe::on('tenant')->find($student->class_id);
        $this->assertSame('5E', $class->level->code);
        $this->assertSame($this->year2->id, $class->academic_year_id);
        $this->assertSame(1, StudentClassEnrollment::on('tenant')
            ->where('student_id', $this->a->id)->where('academic_year_id', $this->year2->id)->count());
    }

    #[Test]
    public function it_backfills_the_current_year_history(): void
    {
        $response = $this->authPostJson('/api/admin/enrollment/reenrollment/backfill', [
            'academic_year_id' => $this->year1->id,
        ]);

        $response->assertOk()->assertJsonPath('data.created', 2);
        $this->assertSame(2, StudentClassEnrollment::on('tenant')->where('academic_year_id', $this->year1->id)->count());
    }
}
