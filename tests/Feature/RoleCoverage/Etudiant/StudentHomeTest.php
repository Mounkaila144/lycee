<?php

namespace Tests\Feature\RoleCoverage\Etudiant;

use Modules\Enrollment\Entities\Student;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Story Étudiant 01 — Portail / Home.
 *
 * Couvre :
 *   - GET /api/frontend/student/me        — profil Student du user connecté
 *   - GET /api/frontend/student/dashboard — KPIs synthétiques (stubs)
 *   - Ownership : student_id JAMAIS via query param, toujours via auth()->user()->student
 *   - Cross-rôle : Caissier/Parent/Professeur bloqués
 */
class StudentHomeTest extends TestCase
{
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function studentWithProfile(): array
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Étudiant');
        $student = Student::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('test-token')->plainTextToken;

        return [$user, $student, $token];
    }

    #[Test]
    public function etudiant_can_view_own_profile(): void
    {
        [, $student, $token] = $this->studentWithProfile();

        $this->withToken($token)
            ->getJson('/api/frontend/student/me')
            ->assertOk()
            ->assertJsonPath('data.id', $student->id)
            ->assertJsonPath('data.firstname', $student->firstname);
    }

    #[Test]
    public function etudiant_without_profile_returns_404(): void
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Étudiant');
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/frontend/student/me')
            ->assertStatus(404)
            ->assertJsonPath('code', 'STUDENT_PROFILE_MISSING');
    }

    #[Test]
    public function etudiant_can_view_own_dashboard(): void
    {
        [, $student, $token] = $this->studentWithProfile();

        $this->withToken($token)
            ->getJson('/api/frontend/student/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'student',
                    'moyenne_actuelle',
                    'next_class',
                    'recent_absences_count',
                    'pending_invoices_count',
                    'available_documents_count',
                ],
            ])
            ->assertJsonPath('data.student.id', $student->id);
    }

    #[Test]
    public function etudiant_query_param_student_id_is_ignored(): void
    {
        // Sécurité critique : un Étudiant qui tente ?student_id=X doit toujours voir SON profil
        [, $myStudent, $token] = $this->studentWithProfile();

        // Créer un autre élève (compte distinct)
        $otherUser = TenantUser::factory()->create(['application' => 'admin']);
        $otherStudent = Student::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->withToken($token)->getJson("/api/frontend/student/me?student_id={$otherStudent->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $myStudent->id);
        $this->assertNotSame($otherStudent->id, $response->json('data.id'));
    }

    #[Test]
    public function professeur_cannot_access_student_endpoints(): void
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Professeur');
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/frontend/student/me')
            ->assertForbidden();
    }

    #[Test]
    public function caissier_cannot_access_student_endpoints(): void
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Caissier');
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/frontend/student/me')
            ->assertForbidden();
    }

    #[Test]
    public function parent_cannot_access_student_endpoints(): void
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Parent');
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/frontend/student/me')
            ->assertForbidden();
    }

    #[Test]
    public function etudiant_can_access_my_grades_stub(): void
    {
        [, $student, $token] = $this->studentWithProfile();

        $this->withToken($token)
            ->getJson('/api/frontend/student/my-grades')
            ->assertOk()
            ->assertJsonPath('meta.student_id', $student->id);
    }

    #[Test]
    public function etudiant_can_access_my_attendance_stub(): void
    {
        [, $student, $token] = $this->studentWithProfile();

        $this->withToken($token)
            ->getJson('/api/frontend/student/my-attendance')
            ->assertOk()
            ->assertJsonPath('meta.student_id', $student->id);
    }

    #[Test]
    public function etudiant_can_access_my_invoices_stub(): void
    {
        [, $student, $token] = $this->studentWithProfile();

        $this->withToken($token)
            ->getJson('/api/frontend/student/my-invoices')
            ->assertOk()
            ->assertJsonPath('meta.student_id', $student->id);
    }

    #[Test]
    public function etudiant_can_access_my_documents_stub(): void
    {
        [, $student, $token] = $this->studentWithProfile();

        $this->withToken($token)
            ->getJson('/api/frontend/student/my-documents')
            ->assertOk()
            ->assertJsonPath('meta.student_id', $student->id);
    }

    #[Test]
    public function professeur_cannot_access_student_grades(): void
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Professeur');
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/frontend/student/my-grades')
            ->assertForbidden();
    }
}
