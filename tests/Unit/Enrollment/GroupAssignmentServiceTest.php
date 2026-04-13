<?php

namespace Tests\Unit\Enrollment;

use Modules\Enrollment\Entities\Group;
use Modules\Enrollment\Entities\GroupAssignment;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Services\GroupAssignmentService;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Programme;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class GroupAssignmentServiceTest extends TestCase
{
    use InteractsWithTenancy;

    private GroupAssignmentService $service;

    private User $user;

    private AcademicYear $academicYear;

    private Programme $programme;

    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->service = new GroupAssignmentService;
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->programme = Programme::factory()->create();
        $this->module = Module::factory()->create(['level' => 'L1']);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    #[Test]
    public function it_can_manually_assign_student_to_group(): void
    {
        $group = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
            'capacity_max' => 35,
        ]);
        $student = Student::factory()->create();

        $result = $this->service->manualAssign($student->id, $group->id, 'Test reason');

        $this->assertNull($result['error']);
        $this->assertNotNull($result['assignment']);
        $this->assertEquals($student->id, $result['assignment']->student_id);
        $this->assertEquals($group->id, $result['assignment']->group_id);
        $this->assertEquals('Manual', $result['assignment']->assignment_method);
    }

    #[Test]
    public function it_prevents_assigning_student_to_full_group(): void
    {
        $group = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
            'capacity_max' => 2,
        ]);

        $students = Student::factory()->count(2)->create();
        foreach ($students as $student) {
            GroupAssignment::factory()->create([
                'student_id' => $student->id,
                'group_id' => $group->id,
                'module_id' => $this->module->id,
                'academic_year_id' => $this->academicYear->id,
            ]);
        }

        $newStudent = Student::factory()->create();
        $result = $this->service->manualAssign($newStudent->id, $group->id);

        $this->assertNotNull($result['error']);
        $this->assertNull($result['assignment']);
        $this->assertStringContainsString('full', strtolower($result['error']));
    }

    #[Test]
    public function it_prevents_duplicate_assignment_to_same_module(): void
    {
        $group1 = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
        $group2 = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
        $student = Student::factory()->create();

        $this->service->manualAssign($student->id, $group1->id);
        $result = $this->service->manualAssign($student->id, $group2->id);

        $this->assertNotNull($result['error']);
        $this->assertNull($result['assignment']);
    }

    #[Test]
    public function it_can_move_student_between_groups(): void
    {
        $group1 = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
        $group2 = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
        $student = Student::factory()->create();

        $this->service->manualAssign($student->id, $group1->id);

        $result = $this->service->moveStudent($student->id, $group1->id, $group2->id, 'Transfer reason');

        $this->assertNull($result['error']);
        $this->assertNotNull($result['assignment']);
        $this->assertEquals($group2->id, $result['assignment']->group_id);
    }

    #[Test]
    public function it_prevents_moving_to_different_module_group(): void
    {
        $otherModule = Module::factory()->create();
        $group1 = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
        $group2 = Group::factory()->create([
            'module_id' => $otherModule->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
        $student = Student::factory()->create();

        $this->service->manualAssign($student->id, $group1->id);
        $result = $this->service->moveStudent($student->id, $group1->id, $group2->id);

        $this->assertNotNull($result['error']);
    }

    #[Test]
    public function it_can_remove_student_assignment(): void
    {
        $group = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
        $student = Student::factory()->create();

        $assignResult = $this->service->manualAssign($student->id, $group->id);
        $result = $this->service->removeStudent($assignResult['assignment']->id);

        $this->assertTrue($result['success']);
        $this->assertNull($result['error']);
    }

    #[Test]
    public function it_can_auto_assign_students_with_balanced_distribution(): void
    {
        $groups = [];
        for ($i = 1; $i <= 3; $i++) {
            $groups[] = Group::factory()->create([
                'module_id' => $this->module->id,
                'program_id' => $this->programme->id,
                'academic_year_id' => $this->academicYear->id,
                'capacity_max' => 10,
            ]);
        }
        $students = Student::factory()->count(9)->create();

        $result = $this->service->autoAssign(
            $students->pluck('id')->toArray(),
            collect($groups)->pluck('id')->toArray(),
            'balanced'
        );

        $this->assertEquals(9, $result['stats']['assigned']);
        $this->assertCount(9, $result['assignments']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function it_can_get_group_statistics(): void
    {
        $group = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
            'capacity_min' => 20,
            'capacity_max' => 35,
        ]);
        $students = Student::factory()->count(10)->create();
        foreach ($students as $student) {
            GroupAssignment::factory()->create([
                'student_id' => $student->id,
                'group_id' => $group->id,
                'module_id' => $this->module->id,
                'academic_year_id' => $this->academicYear->id,
            ]);
        }

        $stats = $this->service->getGroupStats($group->id);

        $this->assertEquals($group->id, $stats['group_id']);
        $this->assertEquals(10, $stats['capacity']['current']);
        $this->assertEquals(25, $stats['capacity']['available']);
        $this->assertFalse($stats['is_full']);
        $this->assertTrue($stats['is_below_minimum']);
    }

    #[Test]
    public function it_returns_empty_array_for_nonexistent_group(): void
    {
        $stats = $this->service->getGroupStats(99999);

        $this->assertEmpty($stats);
    }

    #[Test]
    public function it_auto_assigns_60_students_to_2_groups_equally(): void
    {
        $groups = [];
        for ($i = 1; $i <= 2; $i++) {
            $groups[] = Group::factory()->create([
                'module_id' => $this->module->id,
                'program_id' => $this->programme->id,
                'academic_year_id' => $this->academicYear->id,
                'capacity_max' => 50,
            ]);
        }
        $students = Student::factory()->count(60)->create();

        $result = $this->service->autoAssign(
            $students->pluck('id')->toArray(),
            collect($groups)->pluck('id')->toArray(),
            'balanced'
        );

        $this->assertEquals(60, $result['stats']['assigned']);
        $this->assertEmpty($result['errors']);

        // Check each group has 30 students
        foreach ($groups as $group) {
            $count = GroupAssignment::on('tenant')->where('group_id', $group->id)->count();
            $this->assertEquals(30, $count, "Group {$group->code} should have 30 students");
        }
    }

    #[Test]
    public function it_respects_capacity_max_during_auto_assign(): void
    {
        $group1 = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
            'capacity_max' => 35,
        ]);
        $group2 = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
            'capacity_max' => 35,
        ]);
        $students = Student::factory()->count(60)->create();

        $result = $this->service->autoAssign(
            $students->pluck('id')->toArray(),
            [$group1->id, $group2->id],
            'balanced'
        );

        $this->assertEquals(60, $result['stats']['assigned']);

        $count1 = GroupAssignment::on('tenant')->where('group_id', $group1->id)->count();
        $count2 = GroupAssignment::on('tenant')->where('group_id', $group2->id)->count();

        // Each group should not exceed capacity_max of 35
        $this->assertLessThanOrEqual(35, $count1);
        $this->assertLessThanOrEqual(35, $count2);
    }

    #[Test]
    public function it_calculates_fill_rate_correctly(): void
    {
        $group = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
            'capacity_max' => 35,
        ]);

        $students = Student::factory()->count(30)->create();
        foreach ($students as $student) {
            GroupAssignment::factory()->create([
                'student_id' => $student->id,
                'group_id' => $group->id,
                'module_id' => $this->module->id,
                'academic_year_id' => $this->academicYear->id,
            ]);
        }

        $group->refresh();
        $fillRate = $group->fill_rate;

        // 30/35 = 85.71%
        $this->assertEqualsWithDelta(85.71, $fillRate, 0.01);
    }

    #[Test]
    public function it_can_preview_auto_assign_without_creating_records(): void
    {
        $groups = [];
        for ($i = 1; $i <= 2; $i++) {
            $groups[] = Group::factory()->create([
                'module_id' => $this->module->id,
                'program_id' => $this->programme->id,
                'academic_year_id' => $this->academicYear->id,
                'capacity_max' => 30,
            ]);
        }
        $students = Student::factory()->count(10)->create();

        $result = $this->service->previewAutoAssign(
            $students->pluck('id')->toArray(),
            collect($groups)->pluck('id')->toArray(),
            'balanced'
        );

        $this->assertArrayHasKey('preview', $result);
        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('group_stats', $result);
        $this->assertEquals(10, $result['stats']['will_be_assigned']);
        $this->assertCount(10, $result['preview']);

        // Verify no actual records were created
        $assignmentCount = GroupAssignment::on('tenant')
            ->whereIn('student_id', $students->pluck('id')->toArray())
            ->count();
        $this->assertEquals(0, $assignmentCount);
    }

    #[Test]
    public function it_auto_assigns_students_alphabetically(): void
    {
        $group = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
            'capacity_max' => 50,
        ]);

        $studentA = Student::factory()->create(['lastname' => 'Adamou']);
        $studentZ = Student::factory()->create(['lastname' => 'Zongo']);
        $studentM = Student::factory()->create(['lastname' => 'Mounkaila']);

        $result = $this->service->autoAssign(
            [$studentZ->id, $studentA->id, $studentM->id],
            [$group->id],
            'alphabetic'
        );

        $this->assertEquals(3, $result['stats']['assigned']);

        // Check order of assignments
        $assignments = GroupAssignment::on('tenant')
            ->where('group_id', $group->id)
            ->orderBy('id')
            ->get();

        $lastnames = $assignments->map(fn ($a) => $a->student->lastname)->toArray();
        $sortedLastnames = $lastnames;
        sort($sortedLastnames);
        $this->assertEquals($sortedLastnames, $lastnames);
    }

    #[Test]
    public function it_reports_errors_when_all_groups_are_full(): void
    {
        $group = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
            'capacity_max' => 2,
        ]);

        // Fill the group
        $existingStudents = Student::factory()->count(2)->create();
        foreach ($existingStudents as $student) {
            GroupAssignment::factory()->create([
                'student_id' => $student->id,
                'group_id' => $group->id,
                'module_id' => $this->module->id,
                'academic_year_id' => $this->academicYear->id,
            ]);
        }

        // Try to assign more students
        $newStudents = Student::factory()->count(3)->create();
        $result = $this->service->autoAssign(
            $newStudents->pluck('id')->toArray(),
            [$group->id],
            'balanced'
        );

        $this->assertEquals(0, $result['stats']['assigned']);
        $this->assertEquals(3, $result['stats']['failed']);
        $this->assertCount(3, $result['errors']);
    }
}
