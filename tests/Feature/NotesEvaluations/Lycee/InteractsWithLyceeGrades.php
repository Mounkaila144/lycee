<?php

namespace Tests\Feature\NotesEvaluations\Lycee;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\ClassEvaluation;
use Modules\NotesEvaluations\Entities\ClassEvaluationGrade;
use Modules\NotesEvaluations\Entities\TeacherAssignment;
use Modules\PortailParent\Entities\ParentModel;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\Subject;
use Modules\StructureAcademique\Entities\SubjectClassCoefficient;
use Modules\UsersGuard\Entities\TenantUser;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;

/**
 * Socle des tests « notes lycée » : migrations (socle + NotesEvaluations, Timetable,
 * Attendance, Documents) et jeu de données d'une classe de 4 élèves.
 *
 * Jeu de données (S1 actif, S2) :
 *  - Maths (coef 4, prof $mathTeacher) : D1 /20 poids 1 publié, Composition /40 poids 2 publiée
 *  - Français (coef 3, prof $frenchTeacher) : D1 /10 poids 1 publié
 *  - SVT (sans coefficient → 1, prof $mathTeacher) : D1 /20 poids 1 brouillon
 *  - $headTeacher = professeur principal, sans affectation de matière.
 */
trait InteractsWithLyceeGrades
{
    use InteractsWithTenancy {
        runMigrationsOnce as baseRunMigrationsOnce;
    }

    protected static bool $lyceeMigrationsRan = false;

    protected AcademicYear $year;

    protected Semester $s1;

    protected Semester $s2;

    protected Classe $classe;

    protected Subject $maths;

    protected Subject $french;

    protected Subject $svt;

    protected TenantUser $admin;

    protected TenantUser $mathTeacher;

    protected TenantUser $frenchTeacher;

    protected TenantUser $headTeacher;

    /**
     * @var array<string, Student> élèves A, B, C, D
     */
    protected array $students = [];

    /**
     * @var array<int, string> tokens par users.id
     */
    private array $tokens = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$lyceeMigrationsRan = false;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();
        $this->buildLyceeClass();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    protected function runMigrationsOnce(): void
    {
        $this->baseRunMigrationsOnce();

        if (self::$lyceeMigrationsRan) {
            return;
        }

        foreach ([
            'Modules/NotesEvaluations/Database/Migrations/tenant',
            'Modules/Timetable/Database/Migrations/tenant',
            'Modules/Attendance/Database/Migrations/tenant',
            'Modules/Documents/Database/Migrations/tenant',
        ] as $path) {
            Artisan::call('migrate', [
                '--database' => 'mysql',
                '--path' => base_path($path),
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        self::$lyceeMigrationsRan = true;
    }

    protected function userWithRole(string $role, array $attributes = []): TenantUser
    {
        $user = TenantUser::factory()->create(array_merge(['application' => 'admin'], $attributes));
        $this->assignRole($user, $role);

        return $user;
    }

    protected function tokenFor(TenantUser $user): string
    {
        return $this->tokens[$user->id] ??= $user->createToken('test-token')->plainTextToken;
    }

    protected function getAs(TenantUser $user, string $uri): TestResponse
    {
        return $this->withToken($this->tokenFor($user))->getJson($uri);
    }

    protected function downloadAs(TenantUser $user, string $uri): TestResponse
    {
        return $this->withToken($this->tokenFor($user))->get($uri, ['Accept' => 'application/json']);
    }

    protected function postAs(TenantUser $user, string $uri, array $data = []): TestResponse
    {
        return $this->withToken($this->tokenFor($user))->postJson($uri, $data);
    }

    protected function putAs(TenantUser $user, string $uri, array $data = []): TestResponse
    {
        return $this->withToken($this->tokenFor($user))->putJson($uri, $data);
    }

    protected function deleteAs(TenantUser $user, string $uri): TestResponse
    {
        return $this->withToken($this->tokenFor($user))->deleteJson($uri);
    }

    protected function buildLyceeClass(): void
    {
        $this->year = AcademicYear::factory()->active()->create();
        $this->s1 = Semester::factory()->create([
            'academic_year_id' => $this->year->id, 'name' => 'S1', 'is_active' => true,
            'start_date' => '2025-10-01', 'end_date' => '2026-02-28',
        ]);
        $this->s2 = Semester::factory()->create([
            'academic_year_id' => $this->year->id, 'name' => 'S2', 'is_active' => false,
            'start_date' => '2026-03-01', 'end_date' => '2026-06-30',
        ]);

        $this->admin = $this->userWithRole('Administrator');
        $this->mathTeacher = $this->userWithRole('Professeur', ['firstname' => 'Awa', 'lastname' => 'Maths']);
        $this->frenchTeacher = $this->userWithRole('Professeur', ['firstname' => 'Ibrahim', 'lastname' => 'Lettres']);
        $this->headTeacher = $this->userWithRole('Professeur', ['firstname' => 'Fati', 'lastname' => 'Principale']);

        $level = Level::factory()->create();
        $this->classe = Classe::factory()->create([
            'academic_year_id' => $this->year->id,
            'level_id' => $level->id,
            'series_id' => null,
            'name' => '2nde A',
            'head_teacher_id' => $this->headTeacher->id,
        ]);

        $this->maths = Subject::factory()->create(['name' => 'Mathématiques', 'code' => 'MATH']);
        $this->french = Subject::factory()->create(['name' => 'Français', 'code' => 'FR']);
        $this->svt = Subject::factory()->create(['name' => 'SVT', 'code' => 'SVT']);

        SubjectClassCoefficient::factory()->create(['subject_id' => $this->maths->id, 'level_id' => $level->id, 'series_id' => null, 'coefficient' => 4]);
        SubjectClassCoefficient::factory()->create(['subject_id' => $this->french->id, 'level_id' => $level->id, 'series_id' => null, 'coefficient' => 3]);

        $this->assign($this->mathTeacher, $this->maths);
        $this->assign($this->frenchTeacher, $this->french);
        $this->assign($this->mathTeacher, $this->svt);

        foreach (['A' => 'Alpha', 'B' => 'Bravo', 'C' => 'Charlie', 'D' => 'Delta'] as $key => $lastname) {
            $user = $this->userWithRole('Élève');
            $this->students[$key] = Student::factory()->create([
                'user_id' => $user->id,
                'firstname' => 'Élève',
                'lastname' => $lastname,
                'matricule' => "MAT-{$key}",
            ]);
            $this->enroll($this->students[$key], $this->classe);
        }
    }

    protected function assign(TenantUser $teacher, Subject $subject, ?Classe $classe = null): TeacherAssignment
    {
        $classe ??= $this->classe;

        return TeacherAssignment::query()->create([
            'teacher_id' => $teacher->id,
            'classe_id' => $classe->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $classe->academic_year_id,
        ]);
    }

    protected function enroll(Student $student, Classe $classe, string $status = 'Actif'): void
    {
        ClassEnrollment::on('tenant')->create([
            'student_id' => $student->id,
            'classe_id' => $classe->id,
            'academic_year_id' => $classe->academic_year_id,
            'enrolled_at' => '2025-10-01',
            'assignment_type' => 'Nouvelle inscription',
            'status' => $status,
        ]);
    }

    protected function studentUser(string $key): TenantUser
    {
        return TenantUser::query()->findOrFail($this->students[$key]->user_id);
    }

    protected function parentOf(Student ...$children): TenantUser
    {
        $user = $this->userWithRole('Parent');
        $parent = ParentModel::factory()->create(['user_id' => $user->id]);

        foreach ($children as $child) {
            $parent->students()->attach($child->id, ['is_primary_contact' => true, 'is_financial_responsible' => true]);
        }

        return $user;
    }

    /**
     * @param  array<string, float|null|string>  $scores  clé élève => note (null = non noté, 'abs' = absent)
     */
    protected function evaluation(Subject $subject, array $scores, array $attributes = [], ?Semester $semester = null): ClassEvaluation
    {
        $teacher = $subject->is($this->french) ? $this->frenchTeacher : $this->mathTeacher;

        $evaluation = ClassEvaluation::query()->create(array_merge([
            'classe_id' => $this->classe->id,
            'subject_id' => $subject->id,
            'semester_id' => ($semester ?? $this->s1)->id,
            'teacher_id' => $teacher->id,
            'title' => 'Devoir',
            'type' => 'devoir',
            'evaluation_date' => '2025-11-10',
            'max_score' => 20,
            'weight' => 1,
            'status' => ClassEvaluation::STATUS_PUBLISHED,
            'published_at' => now(),
            'created_by' => $teacher->id,
        ], $attributes));

        foreach ($scores as $key => $score) {
            ClassEvaluationGrade::query()->create([
                'class_evaluation_id' => $evaluation->id,
                'student_id' => $this->students[$key]->id,
                'score' => $score === 'abs' ? null : $score,
                'is_absent' => $score === 'abs',
                'entered_by' => $teacher->id,
            ]);
        }

        return $evaluation;
    }

    /**
     * Jeu de notes de référence (S1) — voir moyennes attendues dans LyceeResultsApiTest.
     */
    protected function seedReferenceGrades(): void
    {
        $this->evaluation($this->maths, ['A' => 13, 'B' => 8, 'C' => 16, 'D' => 'abs'], ['title' => 'Devoir 1']);
        $this->evaluation($this->maths, ['A' => 30, 'B' => 'abs', 'C' => 36, 'D' => 'abs'], [
            'title' => 'Composition', 'type' => 'composition', 'max_score' => 40, 'weight' => 2,
        ]);
        $this->evaluation($this->french, ['A' => 5, 'B' => 6, 'C' => 9, 'D' => 6], ['max_score' => 10]);
        $this->evaluation($this->svt, ['A' => 12.5, 'B' => 0, 'C' => 10, 'D' => 10], [
            'status' => ClassEvaluation::STATUS_DRAFT, 'published_at' => null,
        ]);
    }
}
