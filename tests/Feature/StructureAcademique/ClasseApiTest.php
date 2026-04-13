<?php

namespace Tests\Feature\StructureAcademique;

use Modules\StructureAcademique\Database\Seeders\CycleAndLevelSeeder;
use Modules\StructureAcademique\Database\Seeders\SeriesSeeder;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Cycle;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Entities\Series;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ClasseApiTest extends TestCase
{
    use InteractsWithTenancy;

    private User $user;

    private string $token;

    private AcademicYear $activeYear;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        // Seed base data
        (new CycleAndLevelSeeder)->run();
        (new SeriesSeeder)->run();

        $this->activeYear = AcademicYear::factory()->active()->create(['name' => '2025-2026']);
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
    // Create
    // =============================================

    #[Test]
    public function it_can_create_a_college_class(): void
    {
        $level6e = Level::on('tenant')->where('code', '6E')->first();

        $response = $this->authPostJson('/api/admin/classes', [
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', '6ème A');
    }

    #[Test]
    public function it_can_create_a_lycee_class_with_series(): void
    {
        $levelTle = Level::on('tenant')->where('code', 'TLE')->first();
        $seriesC = Series::on('tenant')->where('code', 'C')->first();

        $response = $this->authPostJson('/api/admin/classes', [
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $levelTle->id,
            'series_id' => $seriesC->id,
            'section' => '1',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Tle C1');
    }

    #[Test]
    public function it_auto_generates_correct_names(): void
    {
        $testCases = [
            ['level_code' => '6E', 'series_code' => null, 'section' => 'A', 'expected' => '6ème A'],
            ['level_code' => '5E', 'series_code' => null, 'section' => 'B', 'expected' => '5ème B'],
            ['level_code' => '4E', 'series_code' => null, 'section' => '1', 'expected' => '4ème 1'],
            ['level_code' => '3E', 'series_code' => null, 'section' => 'A', 'expected' => '3ème A'],
            ['level_code' => '2NDE', 'series_code' => null, 'section' => 'B', 'expected' => '2nde B'],
            ['level_code' => '1ERE', 'series_code' => 'A', 'section' => null, 'expected' => '1ère A'],
            ['level_code' => 'TLE', 'series_code' => 'D', 'section' => '2', 'expected' => 'Tle D2'],
        ];

        foreach ($testCases as $case) {
            $level = Level::on('tenant')->where('code', $case['level_code'])->first();
            $seriesId = null;
            if ($case['series_code']) {
                $seriesId = Series::on('tenant')->where('code', $case['series_code'])->first()->id;
            }

            $response = $this->authPostJson('/api/admin/classes', [
                'academic_year_id' => $this->activeYear->id,
                'level_id' => $level->id,
                'series_id' => $seriesId,
                'section' => $case['section'],
            ]);

            $response->assertStatus(201, "Failed for {$case['expected']}");
            $response->assertJsonPath('data.name', $case['expected']);
        }
    }

    #[Test]
    public function it_requires_series_for_1ere_and_tle(): void
    {
        $level1ere = Level::on('tenant')->where('code', '1ERE')->first();

        $response = $this->authPostJson('/api/admin/classes', [
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level1ere->id,
            'section' => '1',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('series_id');
    }

    #[Test]
    public function it_does_not_require_series_for_college_levels(): void
    {
        $level6e = Level::on('tenant')->where('code', '6E')->first();

        $response = $this->authPostJson('/api/admin/classes', [
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
        ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function it_rejects_duplicate_name_in_same_year(): void
    {
        $level6e = Level::on('tenant')->where('code', '6E')->first();

        $this->authPostJson('/api/admin/classes', [
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
        ]);

        $response = $this->authPostJson('/api/admin/classes', [
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_allows_same_name_in_different_year(): void
    {
        $level6e = Level::on('tenant')->where('code', '6E')->first();
        $otherYear = AcademicYear::factory()->create(['name' => '2024-2025']);

        $response1 = $this->authPostJson('/api/admin/classes', [
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
        ]);
        $response1->assertStatus(201);

        $response2 = $this->authPostJson('/api/admin/classes', [
            'academic_year_id' => $otherYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
        ]);
        $response2->assertStatus(201);
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        $response = $this->authPostJson('/api/admin/classes', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['academic_year_id', 'level_id']);
    }

    #[Test]
    public function it_validates_max_capacity_bounds(): void
    {
        $level6e = Level::on('tenant')->where('code', '6E')->first();

        $response = $this->authPostJson('/api/admin/classes', [
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'max_capacity' => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('max_capacity');
    }

    // =============================================
    // List
    // =============================================

    #[Test]
    public function it_can_list_classes(): void
    {
        $level6e = Level::on('tenant')->where('code', '6E')->first();
        Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'name' => '6ème A',
        ]);

        $response = $this->authGetJson('/api/admin/classes');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_includes_relations_in_list(): void
    {
        $level6e = Level::on('tenant')->where('code', '6E')->first();
        Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'name' => '6ème A',
        ]);

        $response = $this->authGetJson('/api/admin/classes');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'level', 'academic_year_id'],
            ],
        ]);
    }

    // =============================================
    // Show
    // =============================================

    #[Test]
    public function it_can_show_a_class(): void
    {
        $level6e = Level::on('tenant')->where('code', '6E')->first();
        $classe = Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'name' => '6ème A',
        ]);

        $response = $this->authGetJson("/api/admin/classes/{$classe->id}");

        $response->assertOk();
        $response->assertJsonPath('data.name', '6ème A');
        $response->assertJsonStructure(['data' => ['level', 'academic_year']]);
    }

    // =============================================
    // Update
    // =============================================

    #[Test]
    public function it_can_update_class_section_and_regenerates_name(): void
    {
        $level6e = Level::on('tenant')->where('code', '6E')->first();
        $classe = Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'name' => '6ème A',
        ]);

        $response = $this->authPutJson("/api/admin/classes/{$classe->id}", [
            'section' => 'B',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', '6ème B');
    }

    #[Test]
    public function it_can_update_class_capacity(): void
    {
        $level6e = Level::on('tenant')->where('code', '6E')->first();
        $classe = Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'name' => '6ème A',
        ]);

        $response = $this->authPutJson("/api/admin/classes/{$classe->id}", [
            'max_capacity' => 40,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.max_capacity', 40);
    }

    #[Test]
    public function it_can_assign_head_teacher(): void
    {
        $teacher = User::factory()->create();
        $level6e = Level::on('tenant')->where('code', '6E')->first();
        $classe = Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'name' => '6ème A',
        ]);

        $response = $this->authPutJson("/api/admin/classes/{$classe->id}", [
            'head_teacher_id' => $teacher->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.head_teacher.id', $teacher->id);
    }

    // =============================================
    // Delete
    // =============================================

    #[Test]
    public function it_can_delete_a_class_without_students(): void
    {
        $level6e = Level::on('tenant')->where('code', '6E')->first();
        $classe = Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'name' => '6ème A',
        ]);

        $response = $this->authDeleteJson("/api/admin/classes/{$classe->id}");

        $response->assertOk();
        $classe->refresh();
        $this->assertNotNull($classe->deleted_at);
    }

    // =============================================
    // Auth
    // =============================================

    #[Test]
    public function it_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin/classes');
        $response->assertStatus(401);
    }
}
