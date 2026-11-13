<?php

namespace Tests\Feature\Enrollment;

use Modules\Enrollment\Entities\StudentEnrollment;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\TenantUser as User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Régression : /statistics/by-program et /statistics/alerts renvoyaient 500
 * (colonne `program_id` inexistante dans `student_enrollments`, la vraie colonne est `programme_id`).
 */
class EnrollmentStatisticsApiTest extends TestCase
{
    use InteractsWithTenancy;

    private string $token;

    private AcademicYear $year;

    private Programme $programme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        $this->seedRolesAndPermissions();

        $user = User::factory()->create();
        $this->assignRole($user, 'Administrator');
        $this->token = $user->createToken('test-token')->plainTextToken;

        $this->year = AcademicYear::factory()->active()->create();
        $this->programme = Programme::factory()->create(['statut' => 'Actif']);
        $semester = Semester::factory()->create(['academic_year_id' => $this->year->id]);
        StudentEnrollment::factory()->count(3)->create([
            'programme_id' => $this->programme->id,
            'academic_year_id' => $this->year->id,
            'semester_id' => $semester->id,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function authGetJson(string $uri, ?string $token = null): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token ?? $this->token)->getJson($uri);
    }

    #[Test]
    public function by_program_returns_enrollment_counts_per_programme(): void
    {
        $response = $this->authGetJson('/api/admin/enrollment/statistics/by-program');

        $response->assertOk();
        $programme = collect($response->json('data'))->firstWhere('program.id', $this->programme->id);
        $this->assertNotNull($programme);
        $this->assertSame(3, $programme['enrollments']['total']);
    }

    #[Test]
    public function alerts_endpoint_returns_ok(): void
    {
        $this->authGetJson('/api/admin/enrollment/statistics/alerts')->assertOk();
    }

    #[Test]
    public function trends_can_be_filtered_by_programme(): void
    {
        $this->authGetJson("/api/admin/enrollment/statistics/trends?program_id={$this->programme->id}")
            ->assertOk();
    }

    #[Test]
    public function manager_and_professeur_can_read_statistics(): void
    {
        foreach (['Manager', 'Professeur'] as $roleName) {
            $user = User::factory()->create();
            $this->assignRole($user, $roleName);
            $token = $user->createToken('test-token')->plainTextToken;

            $this->authGetJson('/api/admin/enrollment/statistics/by-program', $token)->assertOk();
            $this->authGetJson('/api/admin/enrollment/statistics/alerts', $token)->assertOk();
        }
    }
}
