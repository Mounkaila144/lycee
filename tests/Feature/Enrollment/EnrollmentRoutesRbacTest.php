<?php

namespace Tests\Feature\Enrollment;

use Modules\UsersGuard\Entities\TenantUser as User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * RBAC du groupe /api/admin/enrollment : Professeur en lecture seule,
 * écritures réservées à Administrator|Manager (sauf actions pédagogiques explicites).
 */
class EnrollmentRoutesRbacTest extends TestCase
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

    private function tokenFor(string $roleName): string
    {
        $user = User::factory()->create();
        $this->assignRole($user, $roleName);

        return $user->createToken('test-token')->plainTextToken;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function writeEndpoints(): array
    {
        return [
            'create student' => ['POST', '/api/admin/enrollment/students'],
            'update student' => ['PUT', '/api/admin/enrollment/students/1'],
            'delete student' => ['DELETE', '/api/admin/enrollment/students/1'],
            'change student status' => ['POST', '/api/admin/enrollment/students/1/status'],
            'import students' => ['POST', '/api/admin/enrollment/students/import/confirm'],
            'create enrollment' => ['POST', '/api/admin/enrollment/enrollments'],
            'delete enrollment' => ['DELETE', '/api/admin/enrollment/enrollments/1'],
            'validate enrollment' => ['POST', '/api/admin/enrollment/validation/1/validate'],
            'reject enrollment' => ['POST', '/api/admin/enrollment/validation/1/reject'],
            'create group' => ['POST', '/api/admin/enrollment/groups'],
            'delete group' => ['DELETE', '/api/admin/enrollment/groups/1'],
            'create option' => ['POST', '/api/admin/enrollment/options'],
            'delete option' => ['DELETE', '/api/admin/enrollment/options/1'],
            'generate card' => ['POST', '/api/admin/enrollment/student-cards/generate/1'],
            'clear statistics cache' => ['POST', '/api/admin/enrollment/statistics/clear-cache'],
        ];
    }

    #[Test]
    #[DataProvider('writeEndpoints')]
    public function professeur_cannot_call_write_endpoints(string $method, string $uri): void
    {
        $this->withToken($this->tokenFor('Professeur'))
            ->json($method, $uri, [])
            ->assertForbidden();
    }

    #[Test]
    #[DataProvider('writeEndpoints')]
    public function administrator_passes_role_check_on_write_endpoints(string $method, string $uri): void
    {
        $status = $this->withToken($this->tokenFor('Administrator'))
            ->json($method, $uri, [])
            ->getStatusCode();

        $this->assertNotSame(403, $status, "{$method} {$uri} ne doit pas être refusé à Administrator");
        $this->assertNotSame(401, $status);
    }

    #[Test]
    public function manager_passes_role_check_on_student_creation(): void
    {
        $this->withToken($this->tokenFor('Manager'))
            ->postJson('/api/admin/enrollment/students', [])
            ->assertUnprocessable();
    }

    #[Test]
    public function professeur_can_read_enrollment_resources(): void
    {
        $token = $this->tokenFor('Professeur');

        foreach ([
            '/api/admin/enrollment/students',
            '/api/admin/enrollment/students/statistics/summary',
            '/api/admin/enrollment/groups',
            '/api/admin/enrollment/options',
            '/api/admin/enrollment/student-cards',
        ] as $uri) {
            $status = $this->withToken($token)->getJson($uri)->getStatusCode();
            $this->assertNotSame(403, $status, "GET {$uri} doit rester accessible au Professeur");
            $this->assertLessThan(500, $status, "GET {$uri} ne doit pas renvoyer d'erreur serveur");
        }
    }

    #[Test]
    public function professeur_keeps_access_to_teacher_review_of_exemptions(): void
    {
        $status = $this->withToken($this->tokenFor('Professeur'))
            ->postJson('/api/admin/enrollment/exemptions/1/teacher-review', [])
            ->getStatusCode();

        $this->assertNotSame(403, $status);
    }
}
