<?php

namespace Tests\Feature\NotesEvaluations;

use Modules\NotesEvaluations\Entities\Evaluation;
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

/**
 * A teacher may only create/see/edit grades for the (class, subject) pairs
 * they are assigned to, and never another teacher's evaluations.
 */
class EvaluationTeacherScopeApiTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $teacher;

    private string $teacherToken;

    private Classe $classe;

    private Subject $assignedSubject;

    private Subject $otherSubject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->seedRolesAndPermissions();

        $this->teacher = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($this->teacher, 'Professeur');
        $this->teacherToken = $this->teacher->createToken('test-token')->plainTextToken;

        $year = AcademicYear::factory()->create();
        $level = Level::factory()->create();
        $this->classe = Classe::factory()->create([
            'academic_year_id' => $year->id,
            'level_id' => $level->id,
            'series_id' => null,
        ]);
        $this->assignedSubject = Subject::factory()->create(['code' => 'MATH']);
        $this->otherSubject = Subject::factory()->create(['code' => 'PHYS']);

        TeacherAssignment::on('tenant')->create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->classe->id,
            'subject_id' => $this->assignedSubject->id,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function teacherGetJson(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->teacherToken)->getJson($uri);
    }

    private function teacherPostJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->teacherToken)->postJson($uri, $data);
    }

    private function makeEvaluation(int $subjectId, int $createdBy): Evaluation
    {
        return Evaluation::on('tenant')->create([
            'class_id' => $this->classe->id,
            'subject_id' => $subjectId,
            'semester_id' => null,
            'type' => 'Devoir',
            'name' => 'Test',
            'evaluation_date' => now(),
            'max_score' => 20,
            'coefficient' => 1,
            'created_by' => $createdBy,
            'status' => 'Draft',
        ]);
    }

    #[Test]
    public function teacher_can_create_evaluation_for_assigned_subject(): void
    {
        $this->teacherPostJson('/api/admin/evaluations', [
            'class_id' => $this->classe->id,
            'subject_id' => $this->assignedSubject->id,
            'name' => 'Devoir 1',
            'evaluation_date' => now()->toDateString(),
        ])->assertStatus(201);
    }

    #[Test]
    public function teacher_cannot_create_evaluation_for_unassigned_subject(): void
    {
        $this->teacherPostJson('/api/admin/evaluations', [
            'class_id' => $this->classe->id,
            'subject_id' => $this->otherSubject->id,
            'name' => 'Devoir 1',
            'evaluation_date' => now()->toDateString(),
        ])->assertStatus(403);
    }

    #[Test]
    public function teacher_only_sees_their_own_evaluations(): void
    {
        $other = TenantUser::factory()->create(['application' => 'admin']);
        $this->makeEvaluation($this->assignedSubject->id, $other->id);
        $mine = $this->makeEvaluation($this->assignedSubject->id, $this->teacher->id);

        $response = $this->teacherGetJson("/api/admin/evaluations?class_id={$this->classe->id}&subject_id={$this->assignedSubject->id}");
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$mine->id], $ids);
    }

    #[Test]
    public function teacher_cannot_open_another_teachers_grade_sheet(): void
    {
        $other = TenantUser::factory()->create(['application' => 'admin']);
        $evaluation = $this->makeEvaluation($this->assignedSubject->id, $other->id);

        $this->teacherGetJson("/api/admin/evaluations/{$evaluation->id}/grades")
            ->assertStatus(403);
    }

    #[Test]
    public function teacher_cannot_save_grades_on_another_teachers_evaluation(): void
    {
        $other = TenantUser::factory()->create(['application' => 'admin']);
        $evaluation = $this->makeEvaluation($this->assignedSubject->id, $other->id);

        $this->teacherPostJson("/api/admin/evaluations/{$evaluation->id}/grades", ['grades' => []])
            ->assertStatus(403);
    }

    #[Test]
    public function teacher_cannot_access_bulletins(): void
    {
        $this->teacherGetJson("/api/admin/bulletins?class_id={$this->classe->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function mine_returns_restricted_assignments_for_teacher(): void
    {
        $response = $this->teacherGetJson('/api/admin/my-teaching-assignments');
        $response->assertOk();

        $this->assertTrue($response->json('data.is_restricted'));
        $this->assertCount(1, $response->json('data.assignments'));
    }
}
