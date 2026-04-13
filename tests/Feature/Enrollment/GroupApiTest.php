<?php

namespace Tests\Feature\Enrollment;

use Modules\Enrollment\Entities\Group;
use Modules\Enrollment\Entities\GroupAssignment;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class GroupApiTest extends TestCase
{
    use InteractsWithTenancy;

    private User $user;

    private string $token;

    private AcademicYear $academicYear;

    private Programme $programme;

    private Semester $semester;

    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->programme = Programme::factory()->create();
        $this->semester = Semester::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'S1',
        ]);
        $this->module = Module::factory()->create(['level' => 'L1', 'semester' => 'S1']);
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

    private function authPutJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->putJson($uri, $data);
    }

    private function authDeleteJson(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->deleteJson($uri);
    }

    #[Test]
    public function it_can_list_groups(): void
    {
        Group::factory()->count(3)->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $this->semester->id,
        ]);

        $response = $this->authGetJson('/api/admin/enrollment/groups');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'type', 'level', 'status', 'capacity'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    #[Test]
    public function it_can_filter_groups_by_module(): void
    {
        $otherModule = Module::factory()->create();
        Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
        Group::factory()->create([
            'module_id' => $otherModule->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/groups?module_id={$this->module->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function it_can_filter_groups_by_type(): void
    {
        Group::factory()->td()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
        Group::factory()->tp()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response = $this->authGetJson('/api/admin/enrollment/groups?type=TD');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('TD', $response->json('data.0.type'));
    }

    #[Test]
    public function it_can_create_group(): void
    {
        $response = $this->authPostJson('/api/admin/enrollment/groups', [
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'level' => 'L1',
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $this->semester->id,
            'code' => 'GRP-TD-001',
            'name' => 'Groupe TD 1',
            'type' => 'TD',
            'capacity_min' => 20,
            'capacity_max' => 35,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'code', 'name', 'type', 'level', 'status', 'capacity'],
            ]);

        $this->assertDatabaseHas('groups', [
            'code' => 'GRP-TD-001',
            'name' => 'Groupe TD 1',
            'type' => 'TD',
        ], 'tenant');
    }

    #[Test]
    public function it_validates_required_fields_for_group_creation(): void
    {
        $response = $this->authPostJson('/api/admin/enrollment/groups', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['module_id', 'program_id', 'level', 'academic_year_id', 'code', 'name', 'type']);
    }

    #[Test]
    public function it_can_show_group_details(): void
    {
        $group = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
            'semester_id' => $this->semester->id,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/groups/{$group->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'code', 'name', 'type', 'level', 'status',
                    'capacity', 'module', 'programme', 'academic_year', 'semester',
                ],
            ]);
    }

    #[Test]
    public function it_can_update_group(): void
    {
        $group = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response = $this->authPutJson("/api/admin/enrollment/groups/{$group->id}", [
            'name' => 'Updated Group Name',
            'capacity_max' => 40,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'name' => 'Updated Group Name',
            'capacity_max' => 40,
        ], 'tenant');
    }

    #[Test]
    public function it_can_delete_group(): void
    {
        $group = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response = $this->authDeleteJson("/api/admin/enrollment/groups/{$group->id}");

        $response->assertOk();
        $this->assertSoftDeleted('groups', ['id' => $group->id], 'tenant');
    }

    #[Test]
    public function it_can_assign_student_to_group(): void
    {
        $group = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
        $student = Student::factory()->create();

        $response = $this->authPostJson("/api/admin/enrollment/groups/{$group->id}/assign-student", [
            'student_id' => $student->id,
            'reason' => 'Manual assignment for test',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'student_id', 'group_id', 'assignment_method'],
            ]);

        $this->assertDatabaseHas('group_assignments', [
            'student_id' => $student->id,
            'group_id' => $group->id,
            'assignment_method' => 'Manual',
        ], 'tenant');
    }

    #[Test]
    public function it_prevents_duplicate_assignment(): void
    {
        $group = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
        $student = Student::factory()->create();
        GroupAssignment::factory()->create([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'module_id' => $this->module->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response = $this->authPostJson("/api/admin/enrollment/groups/{$group->id}/assign-student", [
            'student_id' => $student->id,
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_can_list_students_in_group(): void
    {
        $group = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
        $students = Student::factory()->count(3)->create();
        foreach ($students as $student) {
            GroupAssignment::factory()->create([
                'student_id' => $student->id,
                'group_id' => $group->id,
                'module_id' => $this->module->id,
                'academic_year_id' => $this->academicYear->id,
            ]);
        }

        $response = $this->authGetJson("/api/admin/enrollment/groups/{$group->id}/students");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'student_id', 'student', 'assignment_method'],
                ],
                'meta' => ['total', 'group'],
            ]);
        $this->assertCount(3, $response->json('data'));
    }

    #[Test]
    public function it_can_get_group_statistics(): void
    {
        $group = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
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

        $response = $this->authGetJson("/api/admin/enrollment/groups/{$group->id}/statistics");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'group_id', 'code', 'name', 'type', 'status',
                    'capacity' => ['min', 'max', 'current', 'available'],
                    'fill_rate', 'is_full', 'is_below_minimum', 'can_accept_students',
                ],
            ]);
    }

    #[Test]
    public function it_can_remove_student_from_group(): void
    {
        $group = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
        $student = Student::factory()->create();
        $assignment = GroupAssignment::factory()->create([
            'student_id' => $student->id,
            'group_id' => $group->id,
            'module_id' => $this->module->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response = $this->authDeleteJson("/api/admin/enrollment/group-assignments/{$assignment->id}");

        $response->assertOk();
        $this->assertSoftDeleted('group_assignments', ['id' => $assignment->id], 'tenant');
    }

    #[Test]
    public function it_can_auto_assign_students_to_groups(): void
    {
        $groups = [];
        for ($i = 1; $i <= 3; $i++) {
            $groups[] = Group::factory()->td()->create([
                'module_id' => $this->module->id,
                'program_id' => $this->programme->id,
                'academic_year_id' => $this->academicYear->id,
                'code' => "GRP-TD-00{$i}",
                'capacity_max' => 10,
            ]);
        }
        $students = Student::factory()->count(9)->create();

        $response = $this->authPostJson('/api/admin/enrollment/groups/auto-assign', [
            'student_ids' => $students->pluck('id')->toArray(),
            'group_ids' => collect($groups)->pluck('id')->toArray(),
            'method' => 'balanced',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'assignments',
                    'stats' => ['total', 'assigned', 'failed', 'method'],
                    'errors',
                ],
            ]);

        $this->assertEquals(9, $response->json('data.stats.assigned'));
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin/enrollment/groups');

        $response->assertStatus(401);
    }

    #[Test]
    public function it_can_preview_auto_assignment(): void
    {
        $groups = [];
        for ($i = 1; $i <= 2; $i++) {
            $groups[] = Group::factory()->create([
                'module_id' => $this->module->id,
                'program_id' => $this->programme->id,
                'academic_year_id' => $this->academicYear->id,
                'code' => "GRP-TD-00{$i}",
                'capacity_max' => 30,
            ]);
        }
        $students = Student::factory()->count(10)->create();

        $response = $this->authPostJson('/api/admin/enrollment/groups/auto-assign/preview', [
            'student_ids' => $students->pluck('id')->toArray(),
            'group_ids' => collect($groups)->pluck('id')->toArray(),
            'method' => 'balanced',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'preview',
                    'errors',
                    'stats' => ['total_students', 'will_be_assigned', 'will_fail', 'method'],
                    'group_stats',
                ],
            ]);

        // Verify no records were created
        $assignmentCount = GroupAssignment::on('tenant')
            ->whereIn('student_id', $students->pluck('id')->toArray())
            ->count();
        $this->assertEquals(0, $assignmentCount);
    }

    #[Test]
    public function it_can_export_group_students_to_excel(): void
    {
        $group = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
        ]);
        $students = Student::factory()->count(3)->create();
        foreach ($students as $student) {
            GroupAssignment::factory()->create([
                'student_id' => $student->id,
                'group_id' => $group->id,
                'module_id' => $this->module->id,
                'academic_year_id' => $this->academicYear->id,
            ]);
        }

        $response = $this->withToken($this->token)->get("/api/admin/enrollment/groups/{$group->id}/students/export");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    #[Test]
    public function it_can_auto_assign_with_alphabetic_method(): void
    {
        $group = Group::factory()->td()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
            'capacity_max' => 50,
        ]);

        $studentA = Student::factory()->create(['lastname' => 'Adamou']);
        $studentZ = Student::factory()->create(['lastname' => 'Zongo']);
        $studentM = Student::factory()->create(['lastname' => 'Mounkaila']);

        $response = $this->authPostJson('/api/admin/enrollment/groups/auto-assign', [
            'student_ids' => [$studentZ->id, $studentA->id, $studentM->id],
            'group_ids' => [$group->id],
            'method' => 'alphabetic',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.stats.assigned', 3)
            ->assertJsonPath('data.stats.method', 'alphabetic');
    }

    #[Test]
    public function it_returns_422_when_assigning_to_full_group(): void
    {
        $group = Group::factory()->create([
            'module_id' => $this->module->id,
            'program_id' => $this->programme->id,
            'academic_year_id' => $this->academicYear->id,
            'capacity_max' => 2,
        ]);

        $existingStudents = Student::factory()->count(2)->create();
        foreach ($existingStudents as $student) {
            GroupAssignment::factory()->create([
                'student_id' => $student->id,
                'group_id' => $group->id,
                'module_id' => $this->module->id,
                'academic_year_id' => $this->academicYear->id,
            ]);
        }

        $newStudent = Student::factory()->create();
        $response = $this->authPostJson("/api/admin/enrollment/groups/{$group->id}/assign-student", [
            'student_id' => $newStudent->id,
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_can_move_student_to_another_group(): void
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
        GroupAssignment::factory()->create([
            'student_id' => $student->id,
            'group_id' => $group1->id,
            'module_id' => $this->module->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response = $this->authPostJson("/api/admin/enrollment/groups/{$group1->id}/move-student", [
            'student_id' => $student->id,
            'to_group_id' => $group2->id,
            'reason' => 'Demande étudiant',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'student_id', 'group_id'],
            ]);

        $this->assertDatabaseHas('group_assignments', [
            'student_id' => $student->id,
            'group_id' => $group2->id,
        ], 'tenant');
    }
}
