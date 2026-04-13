<?php

namespace Tests\Unit\Enrollment;

use Modules\Enrollment\Entities\PedagogicalEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentEnrollment;
use Modules\Enrollment\Entities\StudentModuleEnrollment;
use Modules\Enrollment\Services\EnrollmentValidationService;
use Modules\Enrollment\Services\PedagogicalContractService;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class EnrollmentValidationServiceTest extends TestCase
{
    use InteractsWithTenancy;

    private EnrollmentValidationService $service;

    private AcademicYear $academicYear;

    private Programme $program;

    private Student $student;

    private User $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $contractService = $this->mock(PedagogicalContractService::class);
        $contractService->shouldReceive('generate')->andReturn('contracts/test.pdf');

        $this->service = new EnrollmentValidationService($contractService);

        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->program = Programme::factory()->create();
        $this->student = Student::factory()->create([
            'status' => 'Actif',
        ]);
        $this->validator = User::factory()->create();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private ?Semester $sharedSemester = null;

    /**
     * Create a complete enrollment with all required module enrollments
     */
    private function createCompleteEnrollment(Student $student, array $overrides = []): PedagogicalEnrollment
    {
        // Reuse semester to avoid unique constraint violations
        if ($this->sharedSemester === null) {
            $this->sharedSemester = Semester::factory()->create([
                'academic_year_id' => $this->academicYear->id,
                'name' => 'S'.fake()->unique()->numberBetween(1, 100),
            ]);
        }
        $semester = $this->sharedSemester;

        $enrollment = PedagogicalEnrollment::factory()->create(array_merge([
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
        ], $overrides));

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

        // Create group assignment to satisfy groups_check
        $group = \Modules\Enrollment\Entities\Group::factory()->create([
            'module_id' => $module->id,
            'program_id' => $this->program->id,
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $semester->id,
            'level' => 'L1',
        ]);

        \Modules\Enrollment\Entities\GroupAssignment::factory()->create([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'module_id' => $module->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        return $enrollment;
    }

    #[Test]
    public function it_checks_enrollment_completeness_with_all_passed(): void
    {
        $enrollment = PedagogicalEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
            'level' => 'L1',
            'status' => PedagogicalEnrollment::STATUS_PENDING,
        ]);

        $checks = $this->service->checkEnrollmentCompleteness($enrollment);

        $this->assertArrayHasKey('administrative', $checks);
        $this->assertArrayHasKey('modules_check', $checks);
        $this->assertArrayHasKey('ects_check', $checks);
        $this->assertArrayHasKey('groups_check', $checks);
        $this->assertArrayHasKey('options_check', $checks);
        $this->assertArrayHasKey('prerequisites_check', $checks);
        $this->assertArrayHasKey('is_complete', $checks);
        $this->assertTrue($checks['administrative']);
    }

    #[Test]
    public function it_fails_administrative_check_when_student_not_active(): void
    {
        $inactiveStudent = Student::factory()->create([
            'status' => 'Suspendu',
        ]);

        $enrollment = PedagogicalEnrollment::factory()->create([
            'student_id' => $inactiveStudent->id,
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
        ]);

        $checks = $this->service->checkEnrollmentCompleteness($enrollment);

        $this->assertFalse($checks['administrative']);
        $this->assertFalse($checks['is_complete']);
    }

    #[Test]
    public function it_validates_enrollment_successfully(): void
    {
        $enrollment = $this->createCompleteEnrollment($this->student);

        $validated = $this->service->validateEnrollment($enrollment, $this->validator);

        $this->assertEquals(PedagogicalEnrollment::STATUS_VALIDATED, $validated->status);
        $this->assertEquals($this->validator->id, $validated->validated_by);
        $this->assertNotNull($validated->validated_at);
        $this->assertNotNull($validated->contract_pdf_path);
    }

    #[Test]
    public function it_throws_exception_when_validating_incomplete_enrollment(): void
    {
        $enrollment = PedagogicalEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
            'status' => PedagogicalEnrollment::STATUS_PENDING,
            'modules_check' => false,
            'groups_check' => false,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/not complete/i');

        $this->service->validateEnrollment($enrollment, $this->validator);
    }

    #[Test]
    public function it_throws_exception_when_validating_already_validated_enrollment(): void
    {
        $enrollment = PedagogicalEnrollment::factory()->validated()->create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/cannot be validated/i');

        $this->service->validateEnrollment($enrollment, $this->validator);
    }

    #[Test]
    public function it_rejects_enrollment_with_reason(): void
    {
        $enrollment = PedagogicalEnrollment::factory()->pending()->create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
        ]);

        $reason = 'Documents incomplets - pièces justificatives manquantes';
        $rejected = $this->service->rejectEnrollment($enrollment, $this->validator, $reason);

        $this->assertEquals(PedagogicalEnrollment::STATUS_REJECTED, $rejected->status);
        $this->assertEquals($reason, $rejected->rejection_reason);
        $this->assertEquals($this->validator->id, $rejected->validated_by);
    }

    #[Test]
    public function it_throws_exception_when_rejecting_already_validated_enrollment(): void
    {
        $enrollment = PedagogicalEnrollment::factory()->validated()->create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/cannot be rejected/i');

        $this->service->rejectEnrollment($enrollment, $this->validator, 'Test reason');
    }

    #[Test]
    public function it_batch_validates_multiple_enrollments(): void
    {
        // Create 3 different students with complete enrollments
        $enrollments = collect();
        for ($i = 0; $i < 3; $i++) {
            $student = Student::factory()->create(['status' => 'Actif']);
            $enrollments->push($this->createCompleteEnrollment($student));
        }

        $results = $this->service->batchValidate(
            $enrollments->pluck('id')->toArray(),
            $this->validator
        );

        $this->assertCount(3, $results['validated']);
        $this->assertEmpty($results['failed']);
    }

    #[Test]
    public function it_reports_failures_in_batch_validation(): void
    {
        // Complete enrollment for one student
        $student1 = Student::factory()->create(['status' => 'Actif']);
        $completeEnrollment = $this->createCompleteEnrollment($student1);

        // Incomplete enrollment for another student (no module enrollments)
        $student2 = Student::factory()->create(['status' => 'Actif']);
        $incompleteEnrollment = PedagogicalEnrollment::factory()->create([
            'student_id' => $student2->id,
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
            'status' => PedagogicalEnrollment::STATUS_PENDING,
            'modules_check' => false,
        ]);

        $results = $this->service->batchValidate(
            [$completeEnrollment->id, $incompleteEnrollment->id],
            $this->validator
        );

        $this->assertCount(1, $results['validated']);
        $this->assertCount(1, $results['failed']);
    }

    #[Test]
    public function it_calculates_validation_statistics(): void
    {
        PedagogicalEnrollment::factory()->validated()->create([
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
        ]);

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

        $stats = $this->service->getValidationStats($this->academicYear->id);

        $this->assertEquals(4, $stats['total']);
        $this->assertEquals(50, $stats['validation_rate']); // 2/4 = 50%
        $this->assertEquals(25, $stats['rejection_rate']); // 1/4 = 25%
        $this->assertEquals(1, $stats['pending_count']);
    }

    #[Test]
    public function it_filters_statistics_by_program(): void
    {
        $otherProgram = Programme::factory()->create();

        PedagogicalEnrollment::factory()->validated()->create([
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $this->program->id,
        ]);

        PedagogicalEnrollment::factory()->validated()->create([
            'academic_year_id' => $this->academicYear->id,
            'program_id' => $otherProgram->id,
        ]);

        $stats = $this->service->getValidationStats($this->academicYear->id, $this->program->id);

        $this->assertEquals(1, $stats['total']);
    }
}
