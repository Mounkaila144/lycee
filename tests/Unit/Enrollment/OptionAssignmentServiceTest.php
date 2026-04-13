<?php

namespace Tests\Unit\Enrollment;

use Modules\Enrollment\Entities\Option;
use Modules\Enrollment\Entities\OptionAssignment;
use Modules\Enrollment\Entities\OptionChoice;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Services\OptionAssignmentService;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class OptionAssignmentServiceTest extends TestCase
{
    use InteractsWithTenancy;

    private OptionAssignmentService $service;

    private Programme $programme;

    private AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->service = new OptionAssignmentService;
        $this->programme = Programme::factory()->create(['statut' => 'Actif']);
        $this->academicYear = AcademicYear::factory()->create(['is_active' => true]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    #[Test]
    public function it_checks_prerequisites_passes_when_no_prerequisites(): void
    {
        $student = Student::factory()->create();
        $option = Option::factory()->create([
            'programme_id' => $this->programme->id,
            'prerequisites' => null,
        ]);

        $result = $this->service->checkPrerequisites($student, $option);

        $this->assertTrue($result['passes']);
        $this->assertEmpty($result['missing']);
    }

    #[Test]
    public function it_checks_prerequisites_returns_missing_when_grade_not_available(): void
    {
        $student = Student::factory()->create();
        $option = Option::factory()->create([
            'programme_id' => $this->programme->id,
            'prerequisites' => [1 => 12.0], // Module 1 needs min 12
        ]);

        $result = $this->service->checkPrerequisites($student, $option);

        // Without Grades module, should return not passing
        $this->assertFalse($result['passes']);
        $this->assertNotEmpty($result['missing']);
    }

    #[Test]
    public function it_assigns_student_manually(): void
    {
        $student = Student::factory()->create();
        $option = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
        ]);
        $admin = User::factory()->create();

        $assignment = $this->service->assignManually(
            $student,
            $option,
            $this->academicYear,
            $admin,
            'Test assignment notes',
            1
        );

        $this->assertInstanceOf(OptionAssignment::class, $assignment);
        $this->assertEquals($student->id, $assignment->student_id);
        $this->assertEquals($option->id, $assignment->option_id);
        $this->assertEquals($this->academicYear->id, $assignment->academic_year_id);
        $this->assertEquals('Manual', $assignment->assignment_method);
        $this->assertEquals($admin->id, $assignment->assigned_by);
        $this->assertEquals('Test assignment notes', $assignment->assignment_notes);
        $this->assertEquals(1, $assignment->choice_rank_obtained);
    }

    #[Test]
    public function it_updates_existing_assignment_on_manual_reassignment(): void
    {
        $student = Student::factory()->create();
        $option1 = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
        ]);
        $option2 = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
        ]);
        $admin = User::factory()->create();

        // First assignment
        $assignment1 = $this->service->assignManually(
            $student,
            $option1,
            $this->academicYear,
            $admin
        );

        // Reassign to different option
        $assignment2 = $this->service->assignManually(
            $student,
            $option2,
            $this->academicYear,
            $admin
        );

        // Should be the same record, updated
        $this->assertEquals($assignment1->id, $assignment2->id);
        $this->assertEquals($option2->id, $assignment2->option_id);

        // Only one assignment should exist for this student/year
        $count = OptionAssignment::where('student_id', $student->id)
            ->where('academic_year_id', $this->academicYear->id)
            ->count();
        $this->assertEquals(1, $count);
    }

    #[Test]
    public function it_removes_assignment(): void
    {
        $student = Student::factory()->create();
        $option = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
        ]);
        $admin = User::factory()->create();

        $assignment = $this->service->assignManually(
            $student,
            $option,
            $this->academicYear,
            $admin
        );

        $result = $this->service->removeAssignment($assignment);

        $this->assertTrue($result);
        $this->assertNull(OptionAssignment::find($assignment->id));
    }

    #[Test]
    public function it_returns_option_statistics(): void
    {
        $option = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
            'capacity' => 30,
        ]);

        // Create some students with choices and assignments
        $students = Student::factory()->count(5)->create();

        foreach ($students as $index => $student) {
            OptionChoice::factory()->create([
                'student_id' => $student->id,
                'option_id' => $option->id,
                'academic_year_id' => $this->academicYear->id,
                'choice_rank' => 1,
                'status' => 'Validated',
            ]);

            OptionAssignment::factory()->create([
                'student_id' => $student->id,
                'option_id' => $option->id,
                'academic_year_id' => $this->academicYear->id,
                'choice_rank_obtained' => 1,
                'assignment_method' => 'Automatic',
            ]);
        }

        $statistics = $this->service->getOptionStatistics($option, $this->academicYear);

        $this->assertEquals($option->id, $statistics['option_id']);
        $this->assertEquals($option->name, $statistics['option_name']);
        $this->assertEquals(30, $statistics['capacity']);
        $this->assertEquals(5, $statistics['total_assigned']);
        $this->assertEquals(25, $statistics['remaining_capacity']);
        $this->assertEquals(5, $statistics['assignment_breakdown']['first_choice_obtained']);
        $this->assertEquals(100, $statistics['satisfaction_rate']);
    }

    #[Test]
    public function it_returns_global_statistics(): void
    {
        $level = 'L3';

        // Create 2 options for the same programme/level
        $option1 = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
            'level' => $level,
            'capacity' => 20,
        ]);

        $option2 = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
            'level' => $level,
            'capacity' => 15,
        ]);

        // Create students with choices
        $students = Student::factory()->count(3)->create();

        foreach ($students as $student) {
            OptionChoice::factory()->create([
                'student_id' => $student->id,
                'option_id' => $option1->id,
                'academic_year_id' => $this->academicYear->id,
                'choice_rank' => 1,
            ]);
        }

        $statistics = $this->service->getGlobalStatistics(
            $this->academicYear,
            $this->programme,
            $level
        );

        $this->assertEquals($this->programme->id, $statistics['programme_id']);
        $this->assertEquals($level, $statistics['level']);
        $this->assertEquals(2, $statistics['total_options']);
        $this->assertEquals(35, $statistics['total_capacity']); // 20 + 15
        $this->assertEquals(3, $statistics['total_students_with_choices']);
        $this->assertCount(2, $statistics['options']);
    }

    #[Test]
    public function automatic_assignment_returns_error_when_no_options(): void
    {
        $result = $this->service->assignOptionsAutomatically(
            $this->academicYear,
            $this->programme,
            'L3'
        );

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Aucune option ouverte', $result['errors'][0]);
    }

    #[Test]
    public function automatic_assignment_returns_error_when_no_choices(): void
    {
        // Create option but no choices
        Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
            'level' => 'L3',
        ]);

        $result = $this->service->assignOptionsAutomatically(
            $this->academicYear,
            $this->programme,
            'L3'
        );

        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Aucun vœu', $result['errors'][0]);
    }

    #[Test]
    public function automatic_assignment_assigns_students_by_choice_rank(): void
    {
        $level = 'L3';

        $option1 = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
            'level' => $level,
            'capacity' => 10,
            'prerequisites' => null,
        ]);

        $option2 = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
            'level' => $level,
            'capacity' => 10,
            'prerequisites' => null,
        ]);

        $student = Student::factory()->create();

        // Student chooses option1 as first choice, option2 as second
        OptionChoice::factory()->create([
            'student_id' => $student->id,
            'option_id' => $option1->id,
            'academic_year_id' => $this->academicYear->id,
            'choice_rank' => 1,
            'status' => 'Pending',
        ]);

        OptionChoice::factory()->create([
            'student_id' => $student->id,
            'option_id' => $option2->id,
            'academic_year_id' => $this->academicYear->id,
            'choice_rank' => 2,
            'status' => 'Pending',
        ]);

        $result = $this->service->assignOptionsAutomatically(
            $this->academicYear,
            $this->programme,
            $level
        );

        $this->assertEquals(1, $result['assigned']);
        $this->assertEmpty($result['errors']);

        // Verify student got first choice
        $assignment = OptionAssignment::where('student_id', $student->id)
            ->where('academic_year_id', $this->academicYear->id)
            ->first();

        $this->assertNotNull($assignment);
        $this->assertEquals($option1->id, $assignment->option_id);
        $this->assertEquals(1, $assignment->choice_rank_obtained);
    }

    #[Test]
    public function automatic_assignment_respects_capacity(): void
    {
        $level = 'L3';

        $option = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
            'level' => $level,
            'capacity' => 2, // Only 2 places
            'prerequisites' => null,
        ]);

        // Create 3 students, all want same option as first choice
        $students = Student::factory()->count(3)->create();

        foreach ($students as $student) {
            OptionChoice::factory()->create([
                'student_id' => $student->id,
                'option_id' => $option->id,
                'academic_year_id' => $this->academicYear->id,
                'choice_rank' => 1,
                'status' => 'Pending',
            ]);
        }

        $result = $this->service->assignOptionsAutomatically(
            $this->academicYear,
            $this->programme,
            $level
        );

        // Only 2 should be assigned (capacity limit)
        $this->assertEquals(2, $result['assigned']);
        // 1 should be unassigned or on waitlist
        $this->assertGreaterThanOrEqual(1, $result['unassigned'] + $result['waitlist']);
    }

    #[Test]
    public function it_calculates_fill_rate_correctly(): void
    {
        $option = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
            'capacity' => 10,
        ]);

        // Assign 5 students (50% fill rate)
        $students = Student::factory()->count(5)->create();
        foreach ($students as $student) {
            OptionAssignment::factory()->create([
                'student_id' => $student->id,
                'option_id' => $option->id,
                'academic_year_id' => $this->academicYear->id,
            ]);
        }

        $statistics = $this->service->getOptionStatistics($option, $this->academicYear);

        $this->assertEquals(50.0, $statistics['fill_rate']);
    }

    #[Test]
    public function it_tracks_assignment_methods_in_statistics(): void
    {
        $option = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
        ]);

        // Create 3 automatic assignments
        $autoStudents = Student::factory()->count(3)->create();
        foreach ($autoStudents as $student) {
            OptionAssignment::factory()->automatic()->create([
                'student_id' => $student->id,
                'option_id' => $option->id,
                'academic_year_id' => $this->academicYear->id,
            ]);
        }

        // Create 2 manual assignments
        $manualStudents = Student::factory()->count(2)->create();
        foreach ($manualStudents as $student) {
            OptionAssignment::factory()->manual()->create([
                'student_id' => $student->id,
                'option_id' => $option->id,
                'academic_year_id' => $this->academicYear->id,
            ]);
        }

        $statistics = $this->service->getOptionStatistics($option, $this->academicYear);

        $this->assertEquals(3, $statistics['method_breakdown']['automatic']);
        $this->assertEquals(2, $statistics['method_breakdown']['manual']);
    }
}
