<?php

namespace Tests\Unit\StructureAcademique;

use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\TeacherModuleAssignment;
use Modules\StructureAcademique\Services\TeacherWorkloadService;
use Modules\UsersGuard\Entities\User;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class TeacherWorkloadServiceTest extends TestCase
{
    use InteractsWithTenancy;

    protected TeacherWorkloadService $service;

    protected User $teacher;

    protected Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        $this->service = new TeacherWorkloadService;

        // Create test teacher
        $this->teacher = User::create([
            'username' => 'prof.test',
            'email' => 'prof.test@example.com',
            'password' => bcrypt('password'),
            'firstname' => 'Jean',
            'lastname' => 'Dupont',
        ]);

        // Create test semester
        $this->semester = Semester::factory()->create([
            'name' => 'S1',
            'is_closed' => false,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Helper pour créer un programme sans User (évite les erreurs de migration)
     */
    private function createProgramme(array $attributes = []): Programme
    {
        return Programme::create(array_merge([
            'code' => 'TEST-'.uniqid(),
            'libelle' => 'Programme Test',
            'type' => 'Licence',
            'duree_annees' => 3,
            'statut' => 'Actif',
        ], $attributes));
    }

    public function test_calculate_workload_with_three_modules(): void
    {
        $programme = $this->createProgramme();
        $modules = Module::factory()->count(3)->create();

        // Create assignments: 30h + 40h + 50h = 120h
        TeacherModuleAssignment::create([
            'teacher_id' => $this->teacher->id,
            'module_id' => $modules[0]->id,
            'programme_id' => $programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
            'type' => 'CM',
            'hours_allocated' => 30,
            'status' => 'Active',
        ]);

        TeacherModuleAssignment::create([
            'teacher_id' => $this->teacher->id,
            'module_id' => $modules[1]->id,
            'programme_id' => $programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
            'type' => 'TD',
            'hours_allocated' => 40,
            'status' => 'Active',
        ]);

        TeacherModuleAssignment::create([
            'teacher_id' => $this->teacher->id,
            'module_id' => $modules[2]->id,
            'programme_id' => $programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
            'type' => 'TP',
            'hours_allocated' => 50,
            'status' => 'Active',
        ]);

        $workload = $this->service->calculateWorkload($this->teacher, $this->semester);

        $this->assertEquals(120, $workload['total_hours']);
        $this->assertEquals(3, $workload['assignments_count']);
        $this->assertEquals(30, $workload['breakdown']['CM']);
        $this->assertEquals(40, $workload['breakdown']['TD']);
        $this->assertEquals(50, $workload['breakdown']['TP']);
    }

    public function test_detect_overload_above_200_hours(): void
    {
        $programme = $this->createProgramme();
        $modules = Module::factory()->count(2)->create();

        // Create assignments: 120h + 100h = 220h > 200h threshold
        TeacherModuleAssignment::create([
            'teacher_id' => $this->teacher->id,
            'module_id' => $modules[0]->id,
            'programme_id' => $programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
            'type' => 'CM',
            'hours_allocated' => 120,
            'status' => 'Active',
        ]);

        TeacherModuleAssignment::create([
            'teacher_id' => $this->teacher->id,
            'module_id' => $modules[1]->id,
            'programme_id' => $programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
            'type' => 'TD',
            'hours_allocated' => 100,
            'status' => 'Active',
        ]);

        $workload = $this->service->calculateWorkload($this->teacher, $this->semester);

        $this->assertTrue($workload['is_overloaded']);
        $this->assertEquals(220, $workload['total_hours']);
    }

    public function test_not_overloaded_below_200_hours(): void
    {
        $programme = $this->createProgramme();
        $module = Module::factory()->create();

        // Create assignment: 150h < 200h threshold
        TeacherModuleAssignment::create([
            'teacher_id' => $this->teacher->id,
            'module_id' => $module->id,
            'programme_id' => $programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
            'type' => 'CM',
            'hours_allocated' => 150,
            'status' => 'Active',
        ]);

        $workload = $this->service->calculateWorkload($this->teacher, $this->semester);

        $this->assertFalse($workload['is_overloaded']);
        $this->assertEquals(150, $workload['total_hours']);
    }

    public function test_complementary_hours_calculation(): void
    {
        $programme = $this->createProgramme();
        $module = Module::factory()->create();

        // Create assignment: 200h - 192h = 8h complementary
        TeacherModuleAssignment::create([
            'teacher_id' => $this->teacher->id,
            'module_id' => $module->id,
            'programme_id' => $programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
            'type' => 'CM',
            'hours_allocated' => 200,
            'status' => 'Active',
        ]);

        $workload = $this->service->calculateWorkload($this->teacher, $this->semester);

        $this->assertEquals(8, $workload['complementary_hours']);
    }

    public function test_no_complementary_hours_below_threshold(): void
    {
        $programme = $this->createProgramme();
        $module = Module::factory()->create();

        // Create assignment: 100h < 192h = 0h complementary
        TeacherModuleAssignment::create([
            'teacher_id' => $this->teacher->id,
            'module_id' => $module->id,
            'programme_id' => $programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
            'type' => 'CM',
            'hours_allocated' => 100,
            'status' => 'Active',
        ]);

        $workload = $this->service->calculateWorkload($this->teacher, $this->semester);

        $this->assertEquals(0, $workload['complementary_hours']);
    }

    public function test_coverage_rate_calculation(): void
    {
        $programme = $this->createProgramme();

        // Create 100 modules
        $modules = Module::factory()->count(100)->create();

        // Assign teachers to 80 modules
        for ($i = 0; $i < 80; $i++) {
            TeacherModuleAssignment::create([
                'teacher_id' => $this->teacher->id,
                'module_id' => $modules[$i]->id,
                'programme_id' => $programme->id,
                'semester_id' => $this->semester->id,
                'level' => 'L1',
                'type' => 'CM',
                'hours_allocated' => 10,
                'status' => 'Active',
            ]);
        }

        $coverageRate = $this->service->getCoverageRate();

        $this->assertEquals(80.0, $coverageRate);
    }

    public function test_coverage_rate_zero_when_no_modules(): void
    {
        $coverageRate = $this->service->getCoverageRate();

        $this->assertEquals(0.0, $coverageRate);
    }

    public function test_only_active_assignments_are_counted(): void
    {
        $programme = $this->createProgramme();
        $modules = Module::factory()->count(3)->create();

        // Active assignment
        TeacherModuleAssignment::create([
            'teacher_id' => $this->teacher->id,
            'module_id' => $modules[0]->id,
            'programme_id' => $programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
            'type' => 'CM',
            'hours_allocated' => 30,
            'status' => 'Active',
        ]);

        // Replaced assignment (should not be counted)
        TeacherModuleAssignment::create([
            'teacher_id' => $this->teacher->id,
            'module_id' => $modules[1]->id,
            'programme_id' => $programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
            'type' => 'TD',
            'hours_allocated' => 40,
            'status' => 'Replaced',
        ]);

        // Cancelled assignment (should not be counted)
        TeacherModuleAssignment::create([
            'teacher_id' => $this->teacher->id,
            'module_id' => $modules[2]->id,
            'programme_id' => $programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
            'type' => 'TP',
            'hours_allocated' => 50,
            'status' => 'Cancelled',
        ]);

        $workload = $this->service->calculateWorkload($this->teacher, $this->semester);

        $this->assertEquals(30, $workload['total_hours']);
        $this->assertEquals(1, $workload['assignments_count']);
    }

    public function test_get_uncovered_modules(): void
    {
        $programme = $this->createProgramme();

        // Create 5 modules
        $modules = Module::factory()->count(5)->create();

        // Assign teachers to only 2 modules
        TeacherModuleAssignment::create([
            'teacher_id' => $this->teacher->id,
            'module_id' => $modules[0]->id,
            'programme_id' => $programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
            'type' => 'CM',
            'hours_allocated' => 10,
            'status' => 'Active',
        ]);

        TeacherModuleAssignment::create([
            'teacher_id' => $this->teacher->id,
            'module_id' => $modules[1]->id,
            'programme_id' => $programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
            'type' => 'TD',
            'hours_allocated' => 10,
            'status' => 'Active',
        ]);

        $uncovered = $this->service->getUncoveredModules();

        $this->assertCount(3, $uncovered);
    }

    public function test_get_overloaded_teachers(): void
    {
        $programme = $this->createProgramme();
        $module = Module::factory()->create();

        // Create an overloaded teacher (220h)
        TeacherModuleAssignment::create([
            'teacher_id' => $this->teacher->id,
            'module_id' => $module->id,
            'programme_id' => $programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
            'type' => 'CM',
            'hours_allocated' => 220,
            'status' => 'Active',
        ]);

        // Create a normal teacher (100h)
        $normalTeacher = User::create([
            'username' => 'prof.normal',
            'email' => 'prof.normal@example.com',
            'password' => bcrypt('password'),
            'firstname' => 'Marie',
            'lastname' => 'Martin',
        ]);

        $module2 = Module::factory()->create();
        TeacherModuleAssignment::create([
            'teacher_id' => $normalTeacher->id,
            'module_id' => $module2->id,
            'programme_id' => $programme->id,
            'semester_id' => $this->semester->id,
            'level' => 'L1',
            'type' => 'TD',
            'hours_allocated' => 100,
            'status' => 'Active',
        ]);

        $overloaded = $this->service->getOverloadedTeachers($this->semester);

        $this->assertCount(1, $overloaded);
        $this->assertEquals($this->teacher->id, $overloaded[0]['teacher']->id);
    }
}
