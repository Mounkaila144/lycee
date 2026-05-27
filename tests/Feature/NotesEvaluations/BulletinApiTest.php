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
use Modules\StructureAcademique\Entities\SubjectClassCoefficient;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class BulletinApiTest extends TestCase
{
    use InteractsWithTenancy;

    private TenantUser $user;

    private string $token;

    private Classe $classe;

    private Semester $semester;

    /** @var array<string, Student> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->seedRolesAndPermissions();

        $this->user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($this->user, 'Administrator');
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $this->seedBulletinData();
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

    private function authGet(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->get($uri);
    }

    private function seedBulletinData(): void
    {
        $year = AcademicYear::factory()->create();
        $this->semester = Semester::factory()->create(['academic_year_id' => $year->id]);
        $level = Level::factory()->create();
        $this->classe = Classe::factory()->create([
            'academic_year_id' => $year->id,
            'level_id' => $level->id,
            'series_id' => null,
        ]);

        $math = Subject::factory()->create(['code' => 'MATH', 'name' => 'Mathématiques']);
        $french = Subject::factory()->create(['code' => 'FRAN', 'name' => 'Français']);

        foreach ([[$math, 4.0], [$french, 5.0]] as [$subject, $coef]) {
            SubjectClassCoefficient::on('tenant')->create([
                'subject_id' => $subject->id,
                'level_id' => $level->id,
                'series_id' => null,
                'coefficient' => $coef,
            ]);
        }

        foreach (['a', 'b', 'c'] as $key) {
            $this->students[$key] = Student::factory()->create([
                'class_id' => $this->classe->id,
                'matricule' => 'STU-'.strtoupper($key),
            ]);
        }

        $scores = [
            'MATH' => ['subject' => $math, 'a' => 16, 'b' => 10, 'c' => 8],
            'FRAN' => ['subject' => $french, 'a' => 12, 'b' => 18, 'c' => 8],
        ];

        foreach ($scores as $config) {
            $evaluation = Evaluation::on('tenant')->create([
                'class_id' => $this->classe->id,
                'subject_id' => $config['subject']->id,
                'semester_id' => $this->semester->id,
                'type' => 'Composition',
                'name' => 'Composition S1',
                'evaluation_date' => now(),
                'max_score' => 20,
                'coefficient' => 1,
                'created_by' => $this->user->id,
                'status' => 'Draft',
            ]);

            foreach (['a', 'b', 'c'] as $key) {
                Grade::on('tenant')->create([
                    'student_id' => $this->students[$key]->id,
                    'evaluation_id' => $evaluation->id,
                    'score' => $config[$key],
                    'is_absent' => false,
                    'entered_by' => $this->user->id,
                    'entered_at' => now(),
                ]);
            }
        }
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/admin/bulletins?class_id='.$this->classe->id)
            ->assertStatus(401);
    }

    #[Test]
    public function it_returns_weighted_averages_and_ranks(): void
    {
        $response = $this->authGetJson("/api/admin/bulletins?class_id={$this->classe->id}&semester_id={$this->semester->id}");

        $response->assertOk();
        $data = $response->json('data');

        $this->assertCount(2, $data['subjects']);
        $this->assertCount(3, $data['students']);

        $byMatricule = collect($data['students'])->keyBy('matricule');

        // B: (10*4 + 18*5) / 9 = 14.44 → rank 1
        $this->assertEqualsWithDelta(14.44, $byMatricule['STU-B']['general_average'], 0.01);
        $this->assertSame(1, $byMatricule['STU-B']['rank']);

        // A: (16*4 + 12*5) / 9 = 13.78 → rank 2
        $this->assertEqualsWithDelta(13.78, $byMatricule['STU-A']['general_average'], 0.01);
        $this->assertSame(2, $byMatricule['STU-A']['rank']);

        // C: (8*4 + 8*5) / 9 = 8.00 → rank 3
        $this->assertEqualsWithDelta(8.0, $byMatricule['STU-C']['general_average'], 0.01);
        $this->assertSame(3, $byMatricule['STU-C']['rank']);

        $this->assertSame(2, $data['stats']['pass_count']);
    }

    #[Test]
    public function it_downloads_a_student_bulletin_pdf(): void
    {
        $student = $this->students['a'];

        $response = $this->authGet("/api/admin/bulletins/{$student->id}/pdf?class_id={$this->classe->id}&semester_id={$this->semester->id}");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function it_validates_the_class_id(): void
    {
        $this->authGetJson('/api/admin/bulletins')
            ->assertStatus(422);
    }
}
