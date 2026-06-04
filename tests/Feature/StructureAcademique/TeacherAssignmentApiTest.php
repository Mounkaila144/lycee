<?php

namespace Tests\Feature\StructureAcademique;

use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Entities\Subject;
use Modules\StructureAcademique\Entities\TeacherAssignment;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class TeacherAssignmentApiTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $admin;

    private string $token;

    private TenantUser $teacher;

    private Classe $classe;

    private Subject $math;

    private Subject $french;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->seedRolesAndPermissions();

        $this->admin = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($this->admin, 'Administrator');
        $this->token = $this->admin->createToken('test-token')->plainTextToken;

        $this->teacher = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($this->teacher, 'Professeur');

        $year = AcademicYear::factory()->create();
        $level = Level::factory()->create();
        $this->classe = Classe::factory()->create([
            'academic_year_id' => $year->id,
            'level_id' => $level->id,
            'series_id' => null,
        ]);
        $this->math = Subject::factory()->create(['code' => 'MATH']);
        $this->french = Subject::factory()->create(['code' => 'FRAN']);
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

    private function authDeleteJson(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->deleteJson($uri);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/admin/teacher-assignments')->assertStatus(401);
    }

    #[Test]
    public function admin_can_assign_multiple_subjects_in_a_class(): void
    {
        $response = $this->authPostJson('/api/admin/teacher-assignments', [
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->classe->id,
            'subject_ids' => [$this->math->id, $this->french->id],
        ]);

        $response->assertStatus(201);
        $this->assertCount(2, $response->json('data'));

        $count = TeacherAssignment::on('tenant')
            ->where('teacher_id', $this->teacher->id)
            ->count();
        $this->assertSame(2, $count);
    }

    #[Test]
    public function assigning_the_same_triple_twice_is_idempotent(): void
    {
        $payload = [
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->classe->id,
            'subject_ids' => [$this->math->id],
        ];

        $this->authPostJson('/api/admin/teacher-assignments', $payload)->assertStatus(201);
        $this->authPostJson('/api/admin/teacher-assignments', $payload)->assertStatus(201);

        $count = TeacherAssignment::on('tenant')
            ->where('teacher_id', $this->teacher->id)
            ->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function it_rejects_assigning_to_a_non_teacher(): void
    {
        $notTeacher = TenantUser::factory()->create(['application' => 'admin']);

        $this->authPostJson('/api/admin/teacher-assignments', [
            'teacher_id' => $notTeacher->id,
            'class_id' => $this->classe->id,
            'subject_ids' => [$this->math->id],
        ])->assertStatus(422);
    }

    #[Test]
    public function index_filters_by_teacher(): void
    {
        TeacherAssignment::on('tenant')->create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->classe->id,
            'subject_id' => $this->math->id,
        ]);

        $response = $this->authGetJson('/api/admin/teacher-assignments?teacher_id='.$this->teacher->id);
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function admin_can_delete_an_assignment(): void
    {
        $assignment = TeacherAssignment::on('tenant')->create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->classe->id,
            'subject_id' => $this->math->id,
        ]);

        $this->authDeleteJson('/api/admin/teacher-assignments/'.$assignment->id)->assertOk();

        $this->assertSame(0, TeacherAssignment::on('tenant')->count());
    }

    #[Test]
    public function mine_is_not_restricted_for_admin(): void
    {
        $response = $this->authGetJson('/api/admin/my-teaching-assignments');
        $response->assertOk();
        $this->assertFalse($response->json('data.is_restricted'));
    }
}
