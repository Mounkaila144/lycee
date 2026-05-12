<?php

namespace Tests\Feature\RoleCoverage\Administrator;

use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Stories Administrator 01-13 — vérification accès Admin.
 *
 * L'Administrator a accès à TOUTES les surfaces du back-office. Ce test garantit
 * que les middlewares `role:` ajoutés pour les autres rôles n'empêchent JAMAIS
 * un Admin de passer (critère §11 du DEV-AGENT-PROMPT).
 *
 * Pattern : `role:Administrator|<autre>` (jamais `role:<autre>` seul).
 */
class AdministratorAccessTest extends TestCase
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

    private function adminToken(): string
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Administrator');

        return $user->createToken('test-token')->plainTextToken;
    }

    /**
     * Story Admin 01 — Dashboard
     */
    #[Test]
    public function admin_can_access_dashboard_and_profile(): void
    {
        $token = $this->adminToken();

        $this->withToken($token)
            ->getJson('/api/admin/auth/me')
            ->assertOk();
    }

    /**
     * Story Admin 02 — Users management (CRUD complet incluant DELETE)
     */
    #[Test]
    public function admin_can_list_create_and_delete_users(): void
    {
        $token = $this->adminToken();

        $this->withToken($token)->getJson('/api/admin/users')->assertOk();

        $other = TenantUser::factory()->create(['application' => 'admin']);
        $this->withToken($token)
            ->deleteJson("/api/admin/users/{$other->id}")
            ->assertSuccessful();
    }

    /**
     * Story Admin 03 — Roles & Permissions
     */
    #[Test]
    public function admin_can_manage_roles(): void
    {
        $token = $this->adminToken();

        $this->withToken($token)->getJson('/api/admin/roles')->assertOk();
        $this->withToken($token)->getJson('/api/admin/permissions')->assertOk();
    }

    /**
     * Story Admin 04 — Academic structure (CRUD)
     */
    #[Test]
    public function admin_can_access_academic_structure(): void
    {
        $token = $this->adminToken();

        $response = $this->withToken($token)->getJson('/api/admin/academic-years');
        $this->assertNotSame(403, $response->getStatusCode());
    }

    /**
     * Story Admin 05 — Enrollments
     */
    #[Test]
    public function admin_can_access_enrollment_students(): void
    {
        $token = $this->adminToken();

        $this->withToken($token)
            ->getJson('/api/admin/enrollment/students')
            ->assertOk();
    }

    #[Test]
    public function admin_can_create_student_via_alias(): void
    {
        $token = $this->adminToken();

        $response = $this->withToken($token)->postJson('/api/admin/students', [
            'firstname' => 'Admin',
            'lastname' => 'Test',
            'birthdate' => '2010-01-01',
            'sex' => 'M',
        ]);

        $response->assertStatus(201);
    }

    /**
     * Story Admin 06 — Grades & Evaluations
     */
    #[Test]
    public function admin_can_access_notes_admin(): void
    {
        $token = $this->adminToken();

        $response = $this->withToken($token)->getJson('/api/admin/grade-validations');
        $this->assertNotSame(403, $response->getStatusCode());
    }

    /**
     * Story Admin 07 — Attendance
     */
    #[Test]
    public function admin_can_access_attendance(): void
    {
        $token = $this->adminToken();

        $response = $this->withToken($token)->getJson('/api/admin/attendance/sessions');
        $this->assertNotSame(403, $response->getStatusCode());
    }

    /**
     * Story Admin 08 — Timetable
     */
    #[Test]
    public function admin_can_access_timetable(): void
    {
        $token = $this->adminToken();

        $response = $this->withToken($token)->getJson('/api/admin/rooms');
        $this->assertNotSame(403, $response->getStatusCode());
    }

    /**
     * Story Admin 09 — Exams
     */
    #[Test]
    public function admin_can_access_exams(): void
    {
        $token = $this->adminToken();

        $response = $this->withToken($token)->getJson('/api/admin/exams/sessions');
        $this->assertNotSame(403, $response->getStatusCode());
    }

    /**
     * Story Admin 10 — Documents
     */
    #[Test]
    public function admin_can_access_documents(): void
    {
        $token = $this->adminToken();

        $response = $this->withToken($token)->getJson('/api/admin/documents/diplomas');
        $this->assertNotSame(403, $response->getStatusCode());
    }

    /**
     * Story Admin 11 — Finance (full access)
     */
    #[Test]
    public function admin_can_access_finance_full(): void
    {
        $token = $this->adminToken();

        $this->withToken($token)->getJson('/api/admin/finance/invoices')->assertOk();

        $response = $this->withToken($token)->postJson('/api/admin/finance/payments/1/refund', ['amount' => 100]);
        $this->assertNotSame(403, $response->getStatusCode(), 'Admin doit pouvoir refund');

        $response = $this->withToken($token)->postJson('/api/admin/finance/collection/write-off/1', []);
        $this->assertNotSame(403, $response->getStatusCode(), 'Admin doit pouvoir write-off');
    }

    /**
     * Story Admin 12 — Payroll
     */
    #[Test]
    public function admin_can_access_payroll(): void
    {
        $token = $this->adminToken();

        $response = $this->withToken($token)->getJson('/api/admin/payroll/employees');
        $this->assertNotSame(403, $response->getStatusCode());
    }

    /**
     * Story Admin — accès teacher endpoints pour debug (cf. Stories Professeur)
     */
    #[Test]
    public function admin_can_access_teacher_endpoints_for_debug(): void
    {
        $token = $this->adminToken();

        $this->withToken($token)
            ->getJson('/api/frontend/teacher/my-modules')
            ->assertOk();
    }

    /**
     * Sanity check : Admin n'est PAS Parent (n'a pas le rôle), donc bloqué
     * sur le portail Parent. Pattern attendu (pas de bypass).
     */
    #[Test]
    public function admin_is_blocked_on_parent_portal(): void
    {
        $token = $this->adminToken();

        $this->withToken($token)
            ->getJson('/api/admin/parent/me')
            ->assertForbidden();
    }
}
