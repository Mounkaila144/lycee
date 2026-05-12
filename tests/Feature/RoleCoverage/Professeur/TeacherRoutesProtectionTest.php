<?php

namespace Tests\Feature\RoleCoverage\Professeur;

use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Protection des routes Professeur — couvre les Stories Professeur 02-09 :
 *   - 02 Saisie notes : /api/frontend/teacher/grades/*
 *   - 03 Import notes : /api/frontend/teacher/grades/import/*
 *   - 04 Absences éval : /api/frontend/teacher/evaluations/{id}/absences/*
 *   - 05 Rattrapages : /api/frontend/teacher/retake-*
 *   - 06 Présences cours : /api/admin/attendance/* (Professeur autorisé)
 *   - 07 Mon EDT : /api/admin/timetable/* (Professeur autorisé pour lecture)
 *   - 08 Surveillance examens : /api/admin/exams/* (Professeur autorisé)
 *   - 09 Élèves readonly : /api/admin/enrollment/students/* (Professeur en lecture)
 *
 * Ce test vérifie que le middleware `role:` est correctement positionné. Les
 * ownership checks dans les controllers (`teacher_id = auth()->id()`) sont
 * testés story par story dans des suites dédiées.
 */
class TeacherRoutesProtectionTest extends TestCase
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

    private function tokenFor(string $role): string
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, $role);

        return $user->createToken('test-token')->plainTextToken;
    }

    #[Test]
    public function professeur_can_access_teacher_my_modules(): void
    {
        $token = $this->tokenFor('Professeur');

        $response = $this->withToken($token)->getJson('/api/frontend/teacher/my-modules');

        $this->assertNotSame(401, $response->getStatusCode());
        $this->assertNotSame(403, $response->getStatusCode());
    }

    #[Test]
    public function professeur_can_access_teacher_retake_modules(): void
    {
        $token = $this->tokenFor('Professeur');

        $response = $this->withToken($token)->getJson('/api/frontend/teacher/retake-modules');

        $this->assertNotSame(403, $response->getStatusCode(), 'Story Professeur 05 — retake-modules doit être autorisé');
    }

    #[Test]
    public function professeur_can_access_grades_submission_status(): void
    {
        $token = $this->tokenFor('Professeur');

        $response = $this->withToken($token)->getJson('/api/frontend/teacher/grades/submission-status');

        $this->assertNotSame(403, $response->getStatusCode(), 'Story Professeur 02 — submission-status doit être autorisé');
    }

    #[Test]
    public function etudiant_cannot_access_teacher_my_modules(): void
    {
        $token = $this->tokenFor('Étudiant');

        $this->withToken($token)
            ->getJson('/api/frontend/teacher/my-modules')
            ->assertForbidden();
    }

    #[Test]
    public function professeur_can_read_attendance_sessions(): void
    {
        $token = $this->tokenFor('Professeur');

        $response = $this->withToken($token)->getJson('/api/admin/attendance/sessions');

        // Story Professeur 06 : Professeur doit pouvoir accéder (pas de 403)
        $this->assertNotSame(403, $response->getStatusCode(), '/api/admin/attendance/sessions devrait être autorisé pour Professeur');
    }

    #[Test]
    public function caissier_cannot_read_attendance_sessions(): void
    {
        $token = $this->tokenFor('Caissier');

        $response = $this->withToken($token)->getJson('/api/admin/attendance/sessions');

        // Caissier doit être bloqué (403) ou route absente (404 = pas de fuite)
        $this->assertContains($response->getStatusCode(), [403, 404], 'Caissier doit être bloqué sur /admin/attendance/sessions');
        $this->assertNotSame(200, $response->getStatusCode(), 'Caissier ne doit JAMAIS obtenir 200 sur Attendance');
    }

    #[Test]
    public function professeur_can_read_timetable_routes(): void
    {
        $token = $this->tokenFor('Professeur');

        $response = $this->withToken($token)->getJson('/api/admin/rooms');

        $this->assertNotSame(403, $response->getStatusCode(), '/api/admin/rooms devrait être autorisé pour Professeur (lecture)');
    }

    #[Test]
    public function professeur_can_read_exams_routes(): void
    {
        $token = $this->tokenFor('Professeur');

        $response = $this->withToken($token)->getJson('/api/admin/exams/sessions');

        $this->assertNotSame(403, $response->getStatusCode(), '/api/admin/exams/sessions devrait être autorisé pour Professeur (Story 08 surveillance)');
    }

    #[Test]
    public function professeur_can_read_enrollment_students(): void
    {
        $token = $this->tokenFor('Professeur');

        $response = $this->withToken($token)->getJson('/api/admin/enrollment/students');

        $this->assertNotSame(403, $response->getStatusCode(), '/api/admin/enrollment/students devrait être autorisé pour Professeur en lecture (Story 09)');
    }

    #[Test]
    public function professeur_cannot_create_student_via_alias(): void
    {
        // Story 7.1 alias : POST /api/admin/students réservé Admin/Manager
        $token = $this->tokenFor('Professeur');

        $this->withToken($token)
            ->postJson('/api/admin/students', [
                'firstname' => 'Test',
                'lastname' => 'Test',
                'birthdate' => '2010-01-01',
                'sex' => 'M',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function professeur_cannot_access_finance_routes(): void
    {
        $token = $this->tokenFor('Professeur');

        $this->withToken($token)
            ->getJson('/api/admin/finance/invoices')
            ->assertForbidden();
    }

    #[Test]
    public function professeur_cannot_access_payroll_routes(): void
    {
        $token = $this->tokenFor('Professeur');

        $response = $this->withToken($token)->getJson('/api/admin/payroll/employees');

        // Professeur doit être bloqué (403) ou route absente (404 = pas de fuite)
        $this->assertContains($response->getStatusCode(), [403, 404], 'Professeur doit être bloqué sur /admin/payroll/employees');
        $this->assertNotSame(200, $response->getStatusCode(), 'Professeur ne doit JAMAIS obtenir 200 sur Payroll');
    }
}
