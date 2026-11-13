<?php

namespace Tests\Feature\NotesEvaluations;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentModuleEnrollment;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\NotesEvaluations\Imports\GradesImport;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\TeacherModuleAssignment;
use Modules\UsersGuard\Entities\TenantUser as User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * RBAC & ownership of the teacher grade-entry endpoints (/api/frontend/teacher/*).
 */
class TeacherGradeAuthorizationTest extends TestCase
{
    use InteractsWithTenancy {
        runMigrationsOnce as runBaseMigrationsOnce;
    }

    private User $teacher;

    private string $teacherToken;

    private ModuleEvaluationConfig $ownEvaluation;

    private ModuleEvaluationConfig $foreignEvaluation;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $this->teacher = User::factory()->create();
        $this->assignRole($this->teacher, 'Professeur');
        $this->teacherToken = $this->teacher->createToken('test-token')->plainTextToken;

        $this->ownEvaluation = ModuleEvaluationConfig::factory()->create();
        $this->foreignEvaluation = ModuleEvaluationConfig::factory()->create([
            'module_id' => Module::factory(),
            'semester_id' => $this->ownEvaluation->semester_id,
        ]);

        $programme = Programme::create([
            'code' => 'PRG-TEST',
            'libelle' => 'Programme test',
            'type' => 'Licence',
            'duree_annees' => 3,
            'statut' => 'Actif',
        ]);

        TeacherModuleAssignment::create([
            'teacher_id' => $this->teacher->id,
            'module_id' => $this->ownEvaluation->module_id,
            'programme_id' => $programme->id,
            'semester_id' => $this->ownEvaluation->semester_id,
            'level' => 'L1',
            'type' => 'CM',
            'hours_allocated' => 20,
            'status' => 'Active',
        ]);

        $this->student = Student::factory()->create();
    }

    /**
     * Run the shared tenant migrations, then the NotesEvaluations tables (not part of the shared set).
     */
    protected function runMigrationsOnce(): void
    {
        if (self::$migrationsRan) {
            return;
        }

        $this->runBaseMigrationsOnce();

        Artisan::call('migrate', [
            '--database' => 'mysql',
            '--path' => base_path('Modules/NotesEvaluations/Database/Migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function teacherGetJson(string $uri): TestResponse
    {
        return $this->withToken($this->teacherToken)->getJson($uri);
    }

    private function teacherPostJson(string $uri, array $data = []): TestResponse
    {
        return $this->withToken($this->teacherToken)->postJson($uri, $data);
    }

    private function tokenForRole(string $roleName): string
    {
        $user = User::factory()->create();
        $this->assignRole($user, $roleName);

        return $user->createToken('test-token')->plainTextToken;
    }

    #[Test]
    public function teacher_can_auto_save_a_grade_for_an_assigned_module(): void
    {
        $response = $this->teacherPostJson('/api/frontend/teacher/grades/auto-save', [
            'student_id' => $this->student->id,
            'evaluation_id' => $this->ownEvaluation->id,
            'score' => 14.5,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('grades', [
            'student_id' => $this->student->id,
            'evaluation_id' => $this->ownEvaluation->id,
            'score' => 14.5,
            'entered_by' => $this->teacher->id,
        ], 'tenant');
    }

    #[Test]
    public function teacher_cannot_auto_save_a_grade_for_a_foreign_module(): void
    {
        $response = $this->teacherPostJson('/api/frontend/teacher/grades/auto-save', [
            'student_id' => $this->student->id,
            'evaluation_id' => $this->foreignEvaluation->id,
            'score' => 12,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('grades', [
            'evaluation_id' => $this->foreignEvaluation->id,
        ], 'tenant');
    }

    #[Test]
    public function teacher_batch_entry_skips_foreign_module_grades(): void
    {
        $response = $this->teacherPostJson('/api/frontend/teacher/grades/batch', [
            'grades' => [
                ['student_id' => $this->student->id, 'evaluation_id' => $this->foreignEvaluation->id, 'score' => 10],
            ],
        ]);

        $response->assertStatus(207)
            ->assertJsonPath('data.created', 0);
        $this->assertDatabaseMissing('grades', [
            'evaluation_id' => $this->foreignEvaluation->id,
        ], 'tenant');
    }

    #[Test]
    public function teacher_cannot_overwrite_a_published_grade_through_auto_save(): void
    {
        Grade::create([
            'student_id' => $this->student->id,
            'evaluation_id' => $this->ownEvaluation->id,
            'score' => 11,
            'is_absent' => false,
            'entered_by' => $this->teacher->id,
            'entered_at' => now(),
            'status' => 'Published',
            'is_visible_to_students' => true,
            'published_at' => now(),
        ]);

        $response = $this->teacherPostJson('/api/frontend/teacher/grades/auto-save', [
            'student_id' => $this->student->id,
            'evaluation_id' => $this->ownEvaluation->id,
            'score' => 19,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('grades', [
            'student_id' => $this->student->id,
            'evaluation_id' => $this->ownEvaluation->id,
            'score' => 11,
        ], 'tenant');
    }

    #[Test]
    public function teacher_can_read_absence_policy_of_an_assigned_evaluation(): void
    {
        $response = $this->teacherGetJson("/api/frontend/teacher/evaluations/{$this->ownEvaluation->id}/absences/policy");

        $response->assertOk()->assertJsonStructure(['data']);
    }

    #[Test]
    public function teacher_cannot_manage_absences_of_a_foreign_evaluation(): void
    {
        $this->teacherGetJson("/api/frontend/teacher/evaluations/{$this->foreignEvaluation->id}/absences")
            ->assertForbidden();

        $this->teacherPostJson("/api/frontend/teacher/evaluations/{$this->foreignEvaluation->id}/absences/mark-absent", [
            'student_ids' => [$this->student->id],
        ])->assertForbidden();

        $this->assertDatabaseMissing('grades', [
            'evaluation_id' => $this->foreignEvaluation->id,
        ], 'tenant');
    }

    #[Test]
    public function unknown_evaluation_returns_not_found_instead_of_server_error(): void
    {
        $this->teacherGetJson('/api/frontend/teacher/evaluations/999999/absences/policy')
            ->assertNotFound();
    }

    #[Test]
    public function student_cannot_access_teacher_grade_endpoints(): void
    {
        $token = $this->tokenForRole('Élève');

        $this->withToken($token)->getJson('/api/frontend/teacher/my-modules')->assertForbidden();
        $this->withToken($token)->postJson('/api/frontend/teacher/grades/auto-save', [
            'student_id' => $this->student->id,
            'evaluation_id' => $this->ownEvaluation->id,
            'score' => 20,
        ])->assertForbidden();
    }

    #[Test]
    public function teacher_and_parent_cannot_access_admin_grade_validation(): void
    {
        $this->teacherGetJson('/api/admin/grade-validations')->assertForbidden();

        $this->withToken($this->tokenForRole('Parent'))
            ->getJson('/api/admin/grade-validations')
            ->assertForbidden();
    }

    #[Test]
    public function admin_gets_not_found_for_unknown_deliberation_session(): void
    {
        $this->withToken($this->tokenForRole('Administrator'))
            ->getJson('/api/admin/deliberations/sessions/999999')
            ->assertNotFound();
    }

    #[Test]
    public function grade_import_in_overwrite_mode_does_not_modify_published_grades(): void
    {
        StudentModuleEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'module_id' => $this->ownEvaluation->module_id,
            'semester_id' => $this->ownEvaluation->semester_id,
            'status' => 'Inscrit',
        ]);

        Grade::create([
            'student_id' => $this->student->id,
            'evaluation_id' => $this->ownEvaluation->id,
            'score' => 8,
            'is_absent' => false,
            'entered_by' => $this->teacher->id,
            'entered_at' => now(),
            'status' => 'Published',
            'is_visible_to_students' => true,
            'published_at' => now(),
        ]);

        $import = new GradesImport($this->ownEvaluation, $this->teacher, 'overwrite');
        $import->collection(collect([
            collect(['matricule' => $this->student->matricule, 'note' => 18]),
        ]));

        $this->assertSame(0, $import->getUpdated());
        $this->assertCount(1, $import->getErrors());
        $this->assertDatabaseHas('grades', [
            'student_id' => $this->student->id,
            'evaluation_id' => $this->ownEvaluation->id,
            'score' => 8,
        ], 'tenant');
    }
}
