<?php

namespace Tests\Feature\NotesEvaluations;

use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\Evaluation;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\Subject;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class EvaluationApiTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $user;

    private string $token;

    private Classe $classe;

    private Subject $subject;

    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($this->user, 'Administrator');
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $year = AcademicYear::factory()->create();
        $this->semester = Semester::factory()->create(['academic_year_id' => $year->id]);
        $level = Level::factory()->create();
        $this->classe = Classe::factory()->create([
            'academic_year_id' => $year->id,
            'level_id' => $level->id,
            'series_id' => null,
        ]);
        $this->subject = Subject::factory()->create(['code' => 'MATH', 'name' => 'Mathématiques']);

        Student::factory()->count(3)->create(['class_id' => $this->classe->id]);
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

    private function createEvaluation(): int
    {
        return $this->authPostJson('/api/admin/evaluations', [
            'class_id' => $this->classe->id,
            'subject_id' => $this->subject->id,
            'semester_id' => $this->semester->id,
            'type' => 'Composition',
            'name' => 'Composition 1',
            'evaluation_date' => now()->format('Y-m-d'),
            'max_score' => 20,
            'coefficient' => 1,
        ])->json('data.id');
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/admin/evaluations')->assertStatus(401);
    }

    #[Test]
    public function it_creates_an_evaluation(): void
    {
        $response = $this->authPostJson('/api/admin/evaluations', [
            'class_id' => $this->classe->id,
            'subject_id' => $this->subject->id,
            'semester_id' => $this->semester->id,
            'type' => 'Devoir',
            'name' => 'Devoir 1',
            'evaluation_date' => now()->format('Y-m-d'),
            'max_score' => 20,
            'coefficient' => 2,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Devoir 1');
        $this->assertTrue(
            Evaluation::on('tenant')->where('name', 'Devoir 1')->where('class_id', $this->classe->id)->exists()
        );
    }

    #[Test]
    public function it_returns_the_grade_sheet_with_all_class_students(): void
    {
        $id = $this->createEvaluation();

        $response = $this->authGetJson("/api/admin/evaluations/{$id}/grades");

        $response->assertOk();
        $this->assertCount(3, $response->json('data.rows'));
    }

    #[Test]
    public function it_saves_grades_and_computes_the_average(): void
    {
        $id = $this->createEvaluation();
        $students = Student::on('tenant')->where('class_id', $this->classe->id)->orderBy('id')->get();

        $payload = ['grades' => [
            ['student_id' => $students[0]->id, 'score' => 12, 'is_absent' => false],
            ['student_id' => $students[1]->id, 'score' => 16, 'is_absent' => false],
            ['student_id' => $students[2]->id, 'score' => null, 'is_absent' => true],
        ]];

        $this->authPostJson("/api/admin/evaluations/{$id}/grades", $payload)->assertOk();

        $this->assertEqualsWithDelta(
            12.0,
            (float) Grade::on('tenant')->where('evaluation_id', $id)->where('student_id', $students[0]->id)->value('score'),
            0.01
        );
        $this->assertSame(2, Grade::on('tenant')->where('evaluation_id', $id)->where('is_absent', false)->count());

        $sheet = $this->authGetJson("/api/admin/evaluations/{$id}/grades")->json('data.stats');
        $this->assertEqualsWithDelta(14.0, $sheet['average'], 0.01);
    }

    #[Test]
    public function it_deletes_an_evaluation(): void
    {
        $id = $this->createEvaluation();

        $this->withToken($this->token)->deleteJson("/api/admin/evaluations/{$id}")->assertOk();

        $this->assertNull(Evaluation::on('tenant')->find($id));
    }
}
