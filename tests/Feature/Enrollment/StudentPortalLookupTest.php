<?php

namespace Tests\Feature\Enrollment;

use Modules\Enrollment\Entities\Student;
use Modules\UsersGuard\Entities\TenantUser as User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Portail élève (/frontend/enrollment) : l'élève est retrouvé via students.user_id
 * (la colonne students.email n'existe plus dans le schéma secondaire).
 */
class StudentPortalLookupTest extends TestCase
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

    #[Test]
    public function student_portal_finds_student_by_linked_user(): void
    {
        $user = User::factory()->create();
        $this->assignRole($user, 'Élève');
        $student = Student::factory()->withMatricule('2026-0500')->create(['user_id' => $user->id]);
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)->getJson('/api/frontend/enrollment/my-groups')
            ->assertOk()
            ->assertJsonPath('meta.student.id', $student->id);

        $this->withToken($token)->getJson('/api/frontend/enrollment/reenrollment/my-status')
            ->assertOk();

        $this->withToken($token)->getJson('/api/frontend/enrollment/my-card/history')
            ->assertOk();
    }

    #[Test]
    public function user_without_student_profile_gets_not_found_instead_of_server_error(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)->getJson('/api/frontend/enrollment/my-groups')
            ->assertNotFound();
    }

    #[Test]
    public function removed_university_endpoints_are_no_longer_routed(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        foreach ([
            '/api/frontend/enrollment/my-enrollment/status',
            '/api/frontend/enrollment/my-enrollment/history',
            '/api/frontend/enrollment/my-enrollment/contract',
            '/api/frontend/enrollment/transfer/programs',
            '/api/frontend/enrollment/exemption/available-modules',
        ] as $uri) {
            $this->withToken($token)->getJson($uri)->assertNotFound();
        }
    }
}
