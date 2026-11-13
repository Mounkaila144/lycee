<?php

namespace Tests\Feature\RoleCoverage\Professeur;

use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Database\Seeders\CycleAndLevelSeeder;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Espace Professeur — « Mes classes » (accueil prof /admin/teacher/home).
 *
 *   - GET /api/frontend/teacher/my-classes : classes de l'année active dont le prof est professeur principal
 *   - GET /api/frontend/teacher/my-classes/{classe}/students : élèves actifs (ownership vérifié)
 */
class TeacherMyClassesTest extends TestCase
{
    use InteractsWithTenancy;

    private AcademicYear $activeYear;

    private Level $level;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        (new CycleAndLevelSeeder)->run();
        $this->level = Level::on('tenant')->where('code', '6E')->firstOrFail();
        $this->activeYear = AcademicYear::factory()->active()->create(['name' => '2025-2026']);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * @return array{0: TenantUser, 1: string}
     */
    private function userWithRole(string $role): array
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, $role);

        return [$user, $user->createToken('test-token')->plainTextToken];
    }

    private function createClasse(string $section, ?int $headTeacherId, ?int $academicYearId = null): Classe
    {
        return Classe::on('tenant')->create([
            'academic_year_id' => $academicYearId ?? $this->activeYear->id,
            'level_id' => $this->level->id,
            'section' => $section,
            'name' => "6ème {$section}",
            'head_teacher_id' => $headTeacherId,
        ]);
    }

    private function enroll(Student $student, Classe $classe, string $status = 'Actif'): void
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

    #[Test]
    public function professeur_sees_only_classes_where_he_is_head_teacher_in_active_year(): void
    {
        [$teacher, $token] = $this->userWithRole('Professeur');
        [$otherTeacher] = $this->userWithRole('Professeur');
        $oldYear = AcademicYear::factory()->create(['name' => '2020-2021', 'is_active' => false]);

        $mine = $this->createClasse('A', $teacher->id);
        $this->createClasse('B', $otherTeacher->id);
        $this->createClasse('C', null);
        $this->createClasse('D', $teacher->id, $oldYear->id);

        $this->enroll(Student::factory()->withMatricule('MAT-001')->create(), $mine);
        $this->enroll(Student::factory()->withMatricule('MAT-002')->create(), $mine);
        $this->enroll(Student::factory()->withMatricule('MAT-003')->create(), $mine, 'Terminé');

        $this->withToken($token)
            ->getJson('/api/frontend/teacher/my-classes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.students_count', 2)
            ->assertJsonPath('data.0.level.code', '6E');
    }

    #[Test]
    public function professeur_without_class_gets_empty_list(): void
    {
        [, $token] = $this->userWithRole('Professeur');

        $this->withToken($token)
            ->getJson('/api/frontend/teacher/my-classes')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function professeur_can_list_active_students_of_his_class(): void
    {
        [$teacher, $token] = $this->userWithRole('Professeur');
        $classe = $this->createClasse('A', $teacher->id);

        $this->enroll(Student::factory()->withMatricule('MAT-002')->create(['lastname' => 'Garba']), $classe);
        $this->enroll(Student::factory()->withMatricule('MAT-001')->create(['lastname' => 'Abdou']), $classe);
        $this->enroll(Student::factory()->withMatricule('MAT-003')->create(), $classe, 'Terminé');

        $this->withToken($token)
            ->getJson("/api/frontend/teacher/my-classes/{$classe->id}/students")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.matricule', 'MAT-001')
            ->assertJsonPath('data.1.matricule', 'MAT-002');
    }

    #[Test]
    public function professeur_cannot_list_students_of_another_class(): void
    {
        [, $token] = $this->userWithRole('Professeur');
        [$otherTeacher] = $this->userWithRole('Professeur');
        $classe = $this->createClasse('B', $otherTeacher->id);

        $this->withToken($token)
            ->getJson("/api/frontend/teacher/my-classes/{$classe->id}/students")
            ->assertForbidden();
    }

    #[Test]
    public function unknown_class_returns_not_found(): void
    {
        [, $token] = $this->userWithRole('Professeur');

        $this->withToken($token)
            ->getJson('/api/frontend/teacher/my-classes/999999/students')
            ->assertNotFound();
    }

    #[Test]
    public function administrator_can_list_students_of_any_class(): void
    {
        [$teacher] = $this->userWithRole('Professeur');
        [, $adminToken] = $this->userWithRole('Administrator');
        $classe = $this->createClasse('A', $teacher->id);
        $this->enroll(Student::factory()->withMatricule('MAT-001')->create(), $classe);

        $this->withToken($adminToken)
            ->getJson("/api/frontend/teacher/my-classes/{$classe->id}/students")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function other_roles_are_forbidden(): void
    {
        foreach (['Manager', 'Élève', 'Parent', 'Caissier', 'Comptable'] as $role) {
            [, $token] = $this->userWithRole($role);

            $this->withToken($token)
                ->getJson('/api/frontend/teacher/my-classes')
                ->assertForbidden();
        }
    }

    #[Test]
    public function guest_is_unauthorized(): void
    {
        $this->getJson('/api/frontend/teacher/my-classes')->assertUnauthorized();
    }
}
