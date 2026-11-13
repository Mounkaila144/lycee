<?php

namespace Tests\Feature\StructureAcademique;

use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\TenantUser;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class SemesterApiTest extends TestCase
{
    use InteractsWithTenancy;

    private User $user;

    private string $token;

    private AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);
        // Rôles créés avant toute lecture : la transaction tenant doit les voir.
        $this->seedRolesAndPermissions();

        $this->user = User::factory()->create();
        $tenantUser = TenantUser::findOrFail($this->user->id);
        $this->assignRole($tenantUser, 'Administrator');
        $this->token = $tenantUser->createToken('test-token')->plainTextToken;

        $this->academicYear = AcademicYear::factory()->create([
            'name' => '2030-2031',
            'start_date' => '2030-10-01',
            'end_date' => '2031-06-30',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function authGetJson(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->getJson($uri);
    }

    private function authPostJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->postJson($uri, $data);
    }

    #[Test]
    public function it_creates_a_semester_without_code(): void
    {
        $response = $this->authPostJson('/api/admin/semesters', [
            'academic_year_id' => $this->academicYear->id,
            'name' => 'S1',
            'start_date' => '2030-10-01',
            'end_date' => '2031-01-31',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'S1');
        $response->assertJsonPath('data.is_closed', false);
    }

    #[Test]
    public function it_rejects_a_semester_name_other_than_s1_or_s2(): void
    {
        $response = $this->authPostJson('/api/admin/semesters', [
            'academic_year_id' => $this->academicYear->id,
            'name' => 'Trimestre 1',
            'start_date' => '2030-10-01',
            'end_date' => '2031-01-31',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    }

    #[Test]
    public function it_rejects_a_duplicate_semester_for_the_same_year(): void
    {
        Semester::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'S1',
        ]);

        $response = $this->authPostJson('/api/admin/semesters', [
            'academic_year_id' => $this->academicYear->id,
            'name' => 'S1',
            'start_date' => '2030-10-01',
            'end_date' => '2031-01-31',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    }

    #[Test]
    public function it_closes_a_semester(): void
    {
        $semester = Semester::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'S1',
            'is_active' => true,
        ]);

        $response = $this->authPostJson("/api/admin/semesters/{$semester->id}/close");

        $response->assertOk();
        $response->assertJsonPath('data.is_closed', true);
        $response->assertJsonPath('data.is_active', false);
        $this->assertNotNull($semester->fresh()->closed_at);

        $this->authGetJson("/api/admin/semesters/{$semester->id}")
            ->assertOk()
            ->assertJsonPath('data.is_closed', true);
    }

    #[Test]
    public function it_refuses_to_close_an_already_closed_semester(): void
    {
        $semester = Semester::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'S1',
            'closed_at' => now(),
        ]);

        $this->authPostJson("/api/admin/semesters/{$semester->id}/close")
            ->assertUnprocessable();
    }

    #[Test]
    public function it_creates_an_academic_period_of_type_rattrapage(): void
    {
        $semester = Semester::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'name' => 'S1',
        ]);

        $response = $this->authPostJson('/api/admin/academic-periods', [
            'semester_id' => $semester->id,
            'name' => 'Session de rattrapage',
            'type' => 'Rattrapage',
            'start_date' => '2031-01-10',
            'end_date' => '2031-01-20',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.type', 'Rattrapage');
    }

    #[Test]
    public function it_exposes_academic_year_status(): void
    {
        AcademicYear::factory()->active()->create(['name' => '2026-2027']);
        AcademicYear::factory()->create([
            'name' => '2020-2021',
            'start_date' => '2020-10-01',
            'end_date' => '2021-06-30',
        ]);

        $response = $this->authGetJson('/api/admin/academic-years?per_page=50');

        $response->assertOk();
        $statuses = collect($response->json('data'))->pluck('status', 'name');
        $this->assertSame('Active', $statuses['2026-2027']);
        $this->assertSame('Terminée', $statuses['2020-2021']);
        $this->assertSame('À venir', $statuses['2030-2031']);
    }
}
