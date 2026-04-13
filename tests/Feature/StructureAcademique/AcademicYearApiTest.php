<?php

namespace Tests\Feature\StructureAcademique;

use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class AcademicYearApiTest extends TestCase
{
    use InteractsWithTenancy;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
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

    private function authPutJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->putJson($uri, $data);
    }

    private function authDeleteJson(string $uri): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->deleteJson($uri);
    }

    // =============================================
    // Story 1.1 - CRUD Années Scolaires
    // =============================================

    #[Test]
    public function it_can_list_academic_years(): void
    {
        AcademicYear::factory()->create(['name' => '2025-2026']);
        AcademicYear::factory()->create(['name' => '2026-2027']);

        $response = $this->authGetJson('/api/admin/academic-years');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_can_create_academic_year_with_auto_semesters(): void
    {
        $response = $this->authPostJson('/api/admin/academic-years', [
            'name' => '2025-2026',
            'start_date' => '2025-10-01',
            'end_date' => '2026-06-30',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', '2025-2026');
        $response->assertJsonCount(2, 'data.semesters');

        // Vérifier S1 et S2 créés
        $semesters = $response->json('data.semesters');
        $names = collect($semesters)->pluck('name')->sort()->values()->all();
        $this->assertEquals(['S1', 'S2'], $names);
    }

    #[Test]
    public function it_creates_semesters_with_correct_dates(): void
    {
        $response = $this->authPostJson('/api/admin/academic-years', [
            'name' => '2025-2026',
            'start_date' => '2025-10-01',
            'end_date' => '2026-06-30',
        ]);

        $response->assertStatus(201);

        $semesters = collect($response->json('data.semesters'));
        $s1 = $semesters->firstWhere('name', 'S1');
        $s2 = $semesters->firstWhere('name', 'S2');

        // S1 starts at year start
        $this->assertEquals('2025-10-01', $s1['start_date']);
        // S2 ends at year end
        $this->assertEquals('2026-06-30', $s2['end_date']);
        // S2 starts after S1 ends
        $this->assertGreaterThan($s1['end_date'], $s2['start_date']);
    }

    #[Test]
    public function it_creates_semesters_with_custom_s1_end_date(): void
    {
        $response = $this->authPostJson('/api/admin/academic-years', [
            'name' => '2025-2026',
            'start_date' => '2025-10-01',
            'end_date' => '2026-06-30',
            'semester1_end_date' => '2026-01-31',
        ]);

        $response->assertStatus(201);

        $semesters = collect($response->json('data.semesters'));
        $s1 = $semesters->firstWhere('name', 'S1');
        $s2 = $semesters->firstWhere('name', 'S2');

        $this->assertEquals('2026-01-31', $s1['end_date']);
        $this->assertEquals('2026-02-01', $s2['start_date']);
    }

    #[Test]
    public function it_requires_unique_name(): void
    {
        AcademicYear::factory()->create(['name' => '2025-2026']);

        $response = $this->authPostJson('/api/admin/academic-years', [
            'name' => '2025-2026',
            'start_date' => '2025-10-01',
            'end_date' => '2026-06-30',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    #[Test]
    public function it_validates_end_date_after_start_date(): void
    {
        $response = $this->authPostJson('/api/admin/academic-years', [
            'name' => '2025-2026',
            'start_date' => '2026-06-30',
            'end_date' => '2025-10-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('end_date');
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        $response = $this->authPostJson('/api/admin/academic-years', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'start_date', 'end_date']);
    }

    #[Test]
    public function it_can_show_academic_year_with_semesters(): void
    {
        $year = AcademicYear::factory()->create(['name' => '2025-2026']);
        Semester::factory()->s1()->create(['academic_year_id' => $year->id]);
        Semester::factory()->s2()->create(['academic_year_id' => $year->id]);

        $response = $this->authGetJson("/api/admin/academic-years/{$year->id}");

        $response->assertOk();
        $response->assertJsonPath('data.name', '2025-2026');
        $response->assertJsonCount(2, 'data.semesters');
    }

    #[Test]
    public function it_can_update_academic_year(): void
    {
        $year = AcademicYear::factory()->create(['name' => '2025-2026']);

        $response = $this->authPutJson("/api/admin/academic-years/{$year->id}", [
            'name' => '2025-2026 Modifiée',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', '2025-2026 Modifiée');
    }

    #[Test]
    public function it_can_delete_academic_year(): void
    {
        $year = AcademicYear::factory()->create(['name' => '2025-2026', 'is_active' => false]);

        $response = $this->authDeleteJson("/api/admin/academic-years/{$year->id}");

        $response->assertOk();
        $year->refresh();
        $this->assertNotNull($year->deleted_at);
    }

    #[Test]
    public function it_cannot_delete_active_academic_year(): void
    {
        $year = AcademicYear::factory()->active()->create(['name' => '2025-2026']);

        $response = $this->authDeleteJson("/api/admin/academic-years/{$year->id}");

        $response->assertStatus(422);
    }

    #[Test]
    public function it_can_sort_academic_years(): void
    {
        AcademicYear::factory()->create(['name' => '2024-2025', 'start_date' => '2024-10-01', 'end_date' => '2025-06-30']);
        AcademicYear::factory()->create(['name' => '2025-2026', 'start_date' => '2025-10-01', 'end_date' => '2026-06-30']);

        $response = $this->authGetJson('/api/admin/academic-years?sort=name&direction=asc');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertEquals(['2024-2025', '2025-2026'], $names);
    }

    // =============================================
    // Story 1.2 - Activation
    // =============================================

    #[Test]
    public function it_can_activate_academic_year(): void
    {
        $year = AcademicYear::factory()->create(['name' => '2025-2026', 'is_active' => false]);

        $response = $this->authPostJson("/api/admin/academic-years/{$year->id}/activate");

        $response->assertOk();
        $response->assertJsonPath('data.is_active', true);

        $year->refresh();
        $this->assertTrue($year->is_active);
    }

    #[Test]
    public function it_deactivates_previous_year_on_activation(): void
    {
        $oldYear = AcademicYear::factory()->active()->create(['name' => '2024-2025']);
        $newYear = AcademicYear::factory()->create(['name' => '2025-2026', 'is_active' => false]);

        $response = $this->authPostJson("/api/admin/academic-years/{$newYear->id}/activate");

        $response->assertOk();

        $oldYear->refresh();
        $newYear->refresh();

        $this->assertFalse($oldYear->is_active);
        $this->assertTrue($newYear->is_active);
    }

    #[Test]
    public function it_ensures_only_one_active_year(): void
    {
        $year1 = AcademicYear::factory()->active()->create(['name' => '2023-2024']);
        $year2 = AcademicYear::factory()->create(['name' => '2024-2025', 'is_active' => false]);
        $year3 = AcademicYear::factory()->create(['name' => '2025-2026', 'is_active' => false]);

        $this->authPostJson("/api/admin/academic-years/{$year3->id}/activate");

        $activeCount = AcademicYear::on('tenant')->where('is_active', true)->count();
        $this->assertEquals(1, $activeCount);

        $year3->refresh();
        $this->assertTrue($year3->is_active);
    }

    #[Test]
    public function it_is_idempotent_when_activating_already_active_year(): void
    {
        $year = AcademicYear::factory()->active()->create(['name' => '2025-2026']);

        $response = $this->authPostJson("/api/admin/academic-years/{$year->id}/activate");

        $response->assertOk();
        $response->assertJsonPath('data.is_active', true);
    }

    #[Test]
    public function it_can_get_active_academic_year(): void
    {
        AcademicYear::factory()->create(['name' => '2024-2025', 'is_active' => false]);
        AcademicYear::factory()->active()->create(['name' => '2025-2026']);

        $response = $this->authGetJson('/api/admin/academic-years/active');

        $response->assertOk();
        $response->assertJsonPath('data.name', '2025-2026');
        $response->assertJsonPath('data.is_active', true);
    }

    #[Test]
    public function it_returns_null_when_no_active_year(): void
    {
        AcademicYear::factory()->create(['name' => '2025-2026', 'is_active' => false]);

        $response = $this->authGetJson('/api/admin/academic-years/active');

        $response->assertOk();
        $response->assertJsonPath('data', null);
    }

    // =============================================
    // Story 1.3 - Semestres
    // =============================================

    #[Test]
    public function it_can_update_semester_dates(): void
    {
        $year = AcademicYear::factory()->create([
            'name' => '2025-2026',
            'start_date' => '2025-10-01',
            'end_date' => '2026-06-30',
        ]);
        $s1 = Semester::factory()->s1()->create([
            'academic_year_id' => $year->id,
            'start_date' => '2025-10-01',
            'end_date' => '2026-02-28',
        ]);
        Semester::factory()->s2()->create([
            'academic_year_id' => $year->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-06-30',
        ]);

        $response = $this->authPutJson("/api/admin/semesters/{$s1->id}", [
            'start_date' => '2025-10-01',
            'end_date' => '2026-02-15',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.end_date', '2026-02-15');
    }

    #[Test]
    public function it_rejects_semester_dates_outside_academic_year(): void
    {
        $year = AcademicYear::factory()->create([
            'name' => '2025-2026',
            'start_date' => '2025-10-01',
            'end_date' => '2026-06-30',
        ]);
        $s1 = Semester::factory()->s1()->create([
            'academic_year_id' => $year->id,
            'start_date' => '2025-10-01',
            'end_date' => '2026-02-28',
        ]);
        Semester::factory()->s2()->create([
            'academic_year_id' => $year->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-06-30',
        ]);

        $response = $this->authPutJson("/api/admin/semesters/{$s1->id}", [
            'start_date' => '2025-09-01',
            'end_date' => '2026-02-28',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('start_date');
    }

    #[Test]
    public function it_rejects_overlapping_semesters(): void
    {
        $year = AcademicYear::factory()->create([
            'name' => '2025-2026',
            'start_date' => '2025-10-01',
            'end_date' => '2026-06-30',
        ]);
        $s1 = Semester::factory()->s1()->create([
            'academic_year_id' => $year->id,
            'start_date' => '2025-10-01',
            'end_date' => '2026-02-28',
        ]);
        Semester::factory()->s2()->create([
            'academic_year_id' => $year->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-06-30',
        ]);

        // Try to set S1 end_date past S2 start_date
        $response = $this->authPutJson("/api/admin/semesters/{$s1->id}", [
            'start_date' => '2025-10-01',
            'end_date' => '2026-04-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('end_date');
    }

    #[Test]
    public function it_can_get_current_semester(): void
    {
        $year = AcademicYear::factory()->create([
            'name' => '2025-2026',
            'start_date' => now()->subMonths(3)->format('Y-m-d'),
            'end_date' => now()->addMonths(6)->format('Y-m-d'),
        ]);
        Semester::factory()->create([
            'academic_year_id' => $year->id,
            'name' => 'S1',
            'start_date' => now()->subMonths(3)->format('Y-m-d'),
            'end_date' => now()->addMonths(1)->format('Y-m-d'),
        ]);

        $response = $this->authGetJson('/api/admin/semesters/current');

        $response->assertOk();
        $response->assertJsonPath('data.name', 'S1');
    }

    // =============================================
    // Auth tests
    // =============================================

    #[Test]
    public function it_requires_authentication_for_academic_years(): void
    {
        $response = $this->getJson('/api/admin/academic-years');
        $response->assertStatus(401);
    }

    #[Test]
    public function it_requires_authentication_for_semesters(): void
    {
        $response = $this->putJson('/api/admin/semesters/1', [
            'start_date' => '2025-10-01',
            'end_date' => '2026-02-28',
        ]);
        $response->assertStatus(401);
    }
}
