<?php

namespace Tests\Feature\RoleCoverage\Etudiant;

use Modules\Enrollment\Entities\Student;
use Modules\UsersGuard\Entities\TenantUser;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Stories Étudiant 03 (Mon EDT), 07 (Ma carte), 08 (Réinscription).
 */
class StudentExtraEndpointsTest extends TestCase
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

        return [$student, $token];
    }

    #[Test]
    public function etudiant_can_view_own_timetable(): void
    {
        [$student, $token] = $this->studentWithProfile();

        $this->withToken($token)
            ->getJson('/api/frontend/student/my-timetable')
            ->assertOk()
            ->assertJsonPath('meta.student_id', $student->id);
    }

    #[Test]
    public function etudiant_can_view_own_card(): void
    {
        [$student, $token] = $this->studentWithProfile();

        $this->withToken($token)
            ->getJson('/api/frontend/student/my-card')
            ->assertOk()
            ->assertJsonPath('data.student_id', $student->id)
            ->assertJsonPath('data.matricule', $student->matricule);
    }

    #[Test]
    public function etudiant_can_view_reenrollment_campaigns(): void
    {
        [$student, $token] = $this->studentWithProfile();

        $this->withToken($token)
            ->getJson('/api/frontend/student/reenrollment')
            ->assertOk()
            ->assertJsonPath('data.student_id', $student->id)
            ->assertJsonStructure(['data' => ['open_campaigns', 'eligible']]);
    }

    #[Test]
    public function professeur_cannot_access_student_timetable(): void
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Professeur');
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/frontend/student/my-timetable')
            ->assertForbidden();
    }

    #[Test]
    public function parent_cannot_access_student_card(): void
    {
        $user = TenantUser::factory()->create(['application' => 'admin']);
        $this->assignRole($user, 'Parent');
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/frontend/student/my-card')
            ->assertForbidden();
    }
}
