<?php

namespace Tests\Feature\Enrollment;

use Modules\Enrollment\Entities\Group;
use Modules\Enrollment\Entities\GroupAssignment;
use Modules\Enrollment\Entities\PedagogicalEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentEnrollment;
use Modules\Enrollment\Entities\StudentModuleEnrollment;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class EnrollmentValidationApiTest extends TestCase
{
    use InteractsWithTenancy;

    private User $user;

    private string $token;

    private AcademicYear $academicYear;

    private Programme $program;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->program = Programme::factory()->create();
        $this->student = Student::factory()->create([
            'status' => 'Actif',
        ]);
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

    /**
     * Create a complete enrollment with all required module and group enrollments
     */
    private function createCompleteEnrollment(Student $student): PedagogicalEnrollment
    {
        $semester = Semester::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'S'.fake()->unique()->numberBetween(1, 100),
        ]);

        $enrollment = PedagogicalEnrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
            'level' => 'L1',
            'semester_id' => $semester->id,
            'status' => PedagogicalEnrollment::STATUS_PENDING,
            'modules_check' => true,
            'groups_check' => true,
            'options_check' => true,
            'prerequisites_check' => true,
            'total_ects' => 30,
        ]);

        // Create student enrollment and module enrollment
        $studentEnrollment = StudentEnrollment::factory()->create([
            'student_id' => $student->id,
            'programme_id' => $this->program->id,
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $semester->id,
            'level' => 'L1',
        ]);

        $module = Module::factory()->create([
            'credits_ects' => 30,
            'level' => 'L1',
        ]);

        StudentModuleEnrollment::factory()->create([
            'student_id' => $student->id,
            'student_enrollment_id' => $studentEnrollment->id,
            'module_id' => $module->id,
            'semester_id' => $semester->id,
        ]);

        // Create group assignment
        $group = Group::factory()->create([
            'module_id' => $module->id,
            'program_id' => $this->program->id,
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $semester->id,
            'level' => 'L1',
        ]);

        GroupAssignment::factory()->create([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'module_id' => $module->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        return $enrollment;
    }

    #[Test]
    public function it_can_list_pending_enrollments(): void
    {
        PedagogicalEnrollment::factory()
            ->count(3)
            ->pending()
            ->create([
                'academic_year_id' => $this->academicYear->id,
                'program_id' => $this->program->id,
            ]);

        $response = $this->authGetJson('/api/admin/enrollment/validation/pending');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'student_id',
                    'program_id',
                    'level',
                    'status',
                    'total_modules',
                    'total_ects',
                    'modules_check',
                    'groups_check',
                    'options_check',
                    'prerequisites_check',
                ],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
    }

    #[Test]
    public function it_can_filter_pending_enrollments_by_academic_year(): void
    {
        $otherYear = AcademicYear::factory()->create();

        PedagogicalEnrollment::factory()->pending()->create([
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
        ]);

        PedagogicalEnrollment::factory()->pending()->create([
            'academic_year_id' => $otherYear->id,
            'program_id' => $this->program->id,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/validation/pending?academic_year_id={$this->academicYear->id}");

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
    }

    #[Test]
    public function it_can_check_enrollment_completeness(): void
    {
        $enrollment = PedagogicalEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
            'status' => PedagogicalEnrollment::STATUS_PENDING,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/validation/{$enrollment->id}/check");

        $response->assertOk();
        $response->assertJsonStructure([
            'enrollment' => ['id', 'status'],
            'checklist' => [
                'checks' => [
                    '*' => ['key', 'label', 'passed', 'icon'],
                ],
                'is_complete',
                'can_validate',
                'missing_count',
            ],
        ]);
    }

    #[Test]
    public function it_can_validate_a_complete_enrollment(): void
    {
        // Mock the contract service to avoid file system issues in tests
        $this->mock(\Modules\Enrollment\Services\PedagogicalContractService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturn('contracts/test.pdf');
        });

        $enrollment = $this->createCompleteEnrollment($this->student);

        $response = $this->authPostJson("/api/admin/enrollment/validation/{$enrollment->id}/validate");

        $response->assertOk();
        $response->assertJsonPath('data.status', PedagogicalEnrollment::STATUS_VALIDATED);
        $this->assertNotNull($response->json('data.validated_at'));
    }

    #[Test]
    public function it_cannot_validate_an_incomplete_enrollment(): void
    {
        $enrollment = PedagogicalEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
            'status' => PedagogicalEnrollment::STATUS_PENDING,
            'modules_check' => false,
            'groups_check' => false,
            'options_check' => true,
            'prerequisites_check' => true,
        ]);

        $response = $this->authPostJson("/api/admin/enrollment/validation/{$enrollment->id}/validate");

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'error']);
    }

    #[Test]
    public function it_can_reject_an_enrollment_with_reason(): void
    {
        $enrollment = PedagogicalEnrollment::factory()->pending()->create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
        ]);

        $response = $this->authPostJson("/api/admin/enrollment/validation/{$enrollment->id}/reject", [
            'rejection_reason' => 'Les documents justificatifs sont incomplets. Veuillez fournir les pièces manquantes.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', PedagogicalEnrollment::STATUS_REJECTED);
        $this->assertNotNull($response->json('data.rejection_reason'));
    }

    #[Test]
    public function it_requires_rejection_reason_when_rejecting(): void
    {
        $enrollment = PedagogicalEnrollment::factory()->pending()->create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
        ]);

        $response = $this->authPostJson("/api/admin/enrollment/validation/{$enrollment->id}/reject", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['rejection_reason']);
    }

    #[Test]
    public function it_requires_minimum_length_for_rejection_reason(): void
    {
        $enrollment = PedagogicalEnrollment::factory()->pending()->create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
        ]);

        $response = $this->authPostJson("/api/admin/enrollment/validation/{$enrollment->id}/reject", [
            'rejection_reason' => 'Too short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['rejection_reason']);
    }

    #[Test]
    public function it_can_batch_validate_multiple_enrollments(): void
    {
        $enrollments = PedagogicalEnrollment::factory()
            ->count(3)
            ->create([
                'student_id' => $this->student->id,
                'academic_year_id' => $this->academicYear->id,
                'program_id' => $this->program->id,
                'level' => 'L1',
                'status' => PedagogicalEnrollment::STATUS_PENDING,
                'modules_check' => true,
                'groups_check' => true,
                'options_check' => true,
                'prerequisites_check' => true,
                'total_ects' => 30,
            ]);

        $response = $this->authPostJson('/api/admin/enrollment/validation/batch-validate', [
            'enrollment_ids' => $enrollments->pluck('id')->toArray(),
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'data' => ['validated', 'failed'],
        ]);
    }

    #[Test]
    public function it_can_get_validation_statistics(): void
    {
        PedagogicalEnrollment::factory()->validated()->create([
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
        ]);

        PedagogicalEnrollment::factory()->pending()->create([
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
        ]);

        PedagogicalEnrollment::factory()->rejected()->create([
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/validation/stats?academic_year_id={$this->academicYear->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'total',
                'by_status',
                'validation_rate',
                'rejection_rate',
                'pending_count',
            ],
        ]);
    }

    #[Test]
    public function it_can_show_enrollment_details(): void
    {
        $enrollment = PedagogicalEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/validation/{$enrollment->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'student_id',
                'program_id',
                'level',
                'status',
            ],
        ]);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin/enrollment/validation/pending');

        $response->assertStatus(401);
    }
}
