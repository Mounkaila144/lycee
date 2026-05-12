<?php

namespace Tests\Feature\RoleCoverage\Manager;

use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Stories Manager 01-09 — vérification RBAC.
 *
 * Manager = lecture transverse + user management (sans DELETE).
 *   ✅ Dashboard, users (CREATE/UPDATE OK), structure académique (lecture),
 *      enrollments, grades (lecture), attendance, timetable, documents, finance (lecture)
 *   ❌ DELETE users (réservé Admin)
 *   ❌ Mutations destructrices (delete grade, etc.)
 *   ❌ Roles management
 */
class ManagerRoutesProtectionTest extends TestCase
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
    public function manager_can_view_dashboard(): void
    {
        $token = $this->tokenFor('Manager');

        $this->withToken($token)
            ->getJson('/api/admin/auth/me')
            ->assertOk();
    }

    #[Test]
    public function manager_can_list_users(): void
    {
        // Story Manager 02
        $token = $this->tokenFor('Manager');

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertOk();
    }

    #[Test]
    public function manager_cannot_delete_users(): void
    {
        // Story Manager 02 — actions interdites
        $other = TenantUser::factory()->create(['application' => 'admin']);
        $token = $this->tokenFor('Manager');

        $this->withToken($token)
            ->deleteJson("/api/admin/users/{$other->id}")
            ->assertForbidden();
    }

    #[Test]
    public function manager_cannot_list_roles(): void
    {
        // Roles management = Admin only
        $token = $this->tokenFor('Manager');

        $this->withToken($token)
            ->getJson('/api/admin/roles')
            ->assertForbidden();
    }

    #[Test]
    public function manager_can_read_enrollment_students(): void
    {
        // Story Manager 04
        $token = $this->tokenFor('Manager');

        $this->withToken($token)
            ->getJson('/api/admin/enrollment/students')
            ->assertOk();
    }

    #[Test]
    public function manager_can_read_notes_admin(): void
    {
        // Story Manager 05 — grades readonly via /api/admin/* notes endpoints
        $token = $this->tokenFor('Manager');

        $response = $this->withToken($token)->getJson('/api/admin/grade-validations');

        $this->assertNotSame(403, $response->getStatusCode(), 'Manager doit lire les notes admin');
    }

    #[Test]
    public function manager_can_read_attendance(): void
    {
        // Story Manager 06
        $token = $this->tokenFor('Manager');

        $response = $this->withToken($token)->getJson('/api/admin/attendance/sessions');

        $this->assertNotSame(403, $response->getStatusCode(), 'Manager doit lire attendance');
    }

    #[Test]
    public function manager_can_read_timetable(): void
    {
        // Story Manager 07
        $token = $this->tokenFor('Manager');

        $response = $this->withToken($token)->getJson('/api/admin/rooms');

        $this->assertNotSame(403, $response->getStatusCode(), 'Manager doit lire timetable');
    }

    #[Test]
    public function manager_can_read_documents(): void
    {
        // Story Manager 08
        $token = $this->tokenFor('Manager');

        $response = $this->withToken($token)->getJson('/api/admin/documents/diplomas');

        $this->assertNotSame(403, $response->getStatusCode(), 'Manager doit lire documents');
    }

    #[Test]
    public function manager_can_read_finance_invoices(): void
    {
        // Story Manager 09 — finance readonly
        $token = $this->tokenFor('Manager');

        $this->withToken($token)
            ->getJson('/api/admin/finance/invoices')
            ->assertOk();
    }

    #[Test]
    public function manager_cannot_access_teacher_endpoints(): void
    {
        $token = $this->tokenFor('Manager');

        $this->withToken($token)
            ->getJson('/api/frontend/teacher/my-modules')
            ->assertForbidden();
    }

    #[Test]
    public function manager_cannot_access_parent_endpoints(): void
    {
        $token = $this->tokenFor('Manager');

        $this->withToken($token)
            ->getJson('/api/admin/parent/me')
            ->assertForbidden();
    }
}
