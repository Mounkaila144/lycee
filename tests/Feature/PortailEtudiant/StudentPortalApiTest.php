<?php

namespace Tests\Feature\PortailEtudiant;

use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\Evaluation;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\Subject;
use Modules\StructureAcademique\Entities\SubjectClassCoefficient;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class StudentPortalApiTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $user;

    private string $token;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($this->user, 'Élève');
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $year = AcademicYear::factory()->create();
        $semester = Semester::factory()->create(['academic_year_id' => $year->id]);
        $level = Level::factory()->create();
        $classe = Classe::factory()->create([
            'academic_year_id' => $year->id,
            'level_id' => $level->id,
            'series_id' => null,
        ]);

        $this->student = Student::factory()->create([
            'class_id' => $classe->id,
            'user_id' => $this->user->id,
            'matricule' => 'STU-PORTAL-1',
        ]);

        $math = Subject::factory()->create(['code' => 'MATH', 'name' => 'Mathématiques']);
        SubjectClassCoefficient::on('tenant')->create([
            'subject_id' => $math->id,
            'level_id' => $level->id,
            'series_id' => null,
            'coefficient' => 4.0,
        ]);

        $evaluation = Evaluation::on('tenant')->create([
            'class_id' => $classe->id,
            'subject_id' => $math->id,
            'semester_id' => $semester->id,
            'type' => 'Composition',
            'name' => 'Composition',
            'evaluation_date' => now(),
            'max_score' => 20,
            'coefficient' => 1,
            'created_by' => $this->user->id,
            'status' => 'Draft',
        ]);
        Grade::on('tenant')->create([
            'student_id' => $this->student->id,
            'evaluation_id' => $evaluation->id,
            'score' => 15,
            'is_absent' => false,
            'entered_by' => $this->user->id,
            'entered_at' => now(),
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

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/frontend/student/my-grades')->assertStatus(401);
    }

    #[Test]
    public function it_returns_the_linked_student_profile(): void
    {
        $this->authGetJson('/api/frontend/student/me')
            ->assertOk()
            ->assertJsonPath('data.matricule', 'STU-PORTAL-1');
    }

    #[Test]
    public function it_returns_the_student_own_grades(): void
    {
        $response = $this->authGetJson('/api/frontend/student/my-grades');

        $response->assertOk();
        $response->assertJsonPath('data.student.general_average', 15);
        $this->assertNotEmpty($response->json('data.subjects'));
    }

    #[Test]
    public function it_returns_a_dashboard_with_kpis(): void
    {
        $response = $this->authGetJson('/api/frontend/student/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.general_average', 15);
    }
}
