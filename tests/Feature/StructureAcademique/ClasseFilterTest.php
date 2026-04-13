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

class ClasseFilterTest extends TestCase
{
    use InteractsWithTenancy;

    private User $user;

    private string $token;

    private AcademicYear $activeYear;

    private Cycle $college;

    private Cycle $lycee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        (new CycleAndLevelSeeder)->run();
        (new SeriesSeeder)->run();

        $this->activeYear = AcademicYear::factory()->active()->create(['name' => '2025-2026']);
        $this->college = Cycle::on('tenant')->where('code', 'COL')->first();
        $this->lycee = Cycle::on('tenant')->where('code', 'LYC')->first();

        $this->seedClasses();
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

    /**
     * Create test classes for filtering
     */
    private function seedClasses(): void
    {
        $level6e = Level::on('tenant')->where('code', '6E')->first();
        $level5e = Level::on('tenant')->where('code', '5E')->first();
        $levelTle = Level::on('tenant')->where('code', 'TLE')->first();
        $level2nde = Level::on('tenant')->where('code', '2NDE')->first();
        $seriesA = Series::on('tenant')->where('code', 'A')->first();
        $seriesC = Series::on('tenant')->where('code', 'C')->first();

        // Collège classes
        Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'name' => '6ème A',
        ]);
        Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'B',
            'name' => '6ème B',
        ]);
        Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level5e->id,
            'section' => 'A',
            'name' => '5ème A',
        ]);

        // Lycée classes
        Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level2nde->id,
            'section' => 'A',
            'name' => '2nde A',
        ]);
        Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $levelTle->id,
            'series_id' => $seriesA->id,
            'section' => '1',
            'name' => 'Tle A1',
        ]);
        Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $levelTle->id,
            'series_id' => $seriesC->id,
            'section' => '1',
            'name' => 'Tle C1',
        ]);
    }

    // =============================================
    // Filter by cycle
    // =============================================

    #[Test]
    public function it_filters_classes_by_college_cycle(): void
    {
        $response = $this->authGetJson("/api/admin/classes?cycle_id={$this->college->id}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data'); // 6ème A, 6ème B, 5ème A
    }

    #[Test]
    public function it_filters_classes_by_lycee_cycle(): void
    {
        $response = $this->authGetJson("/api/admin/classes?cycle_id={$this->lycee->id}");

        $response->assertOk();
        $response->assertJsonCount(3, 'data'); // 2nde A, Tle A1, Tle C1
    }

    // =============================================
    // Filter by level
    // =============================================

    #[Test]
    public function it_filters_classes_by_level(): void
    {
        $level6e = Level::on('tenant')->where('code', '6E')->first();

        $response = $this->authGetJson("/api/admin/classes?level_id={$level6e->id}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data'); // 6ème A, 6ème B
    }

    // =============================================
    // Filter by series
    // =============================================

    #[Test]
    public function it_filters_classes_by_series(): void
    {
        $seriesC = Series::on('tenant')->where('code', 'C')->first();

        $response = $this->authGetJson("/api/admin/classes?series_id={$seriesC->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data'); // Tle C1
    }

    // =============================================
    // Combined filters
    // =============================================

    #[Test]
    public function it_combines_cycle_and_level_filters(): void
    {
        $level6e = Level::on('tenant')->where('code', '6E')->first();

        $response = $this->authGetJson("/api/admin/classes?cycle_id={$this->college->id}&level_id={$level6e->id}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_combines_cycle_level_and_series_filters(): void
    {
        $levelTle = Level::on('tenant')->where('code', 'TLE')->first();
        $seriesA = Series::on('tenant')->where('code', 'A')->first();

        $response = $this->authGetJson("/api/admin/classes?cycle_id={$this->lycee->id}&level_id={$levelTle->id}&series_id={$seriesA->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Tle A1');
    }

    // =============================================
    // Filter by academic year
    // =============================================

    #[Test]
    public function it_defaults_to_active_year(): void
    {
        // Create classes in another year
        $otherYear = AcademicYear::factory()->create(['name' => '2024-2025']);
        $level6e = Level::on('tenant')->where('code', '6E')->first();
        Classe::on('tenant')->create([
            'academic_year_id' => $otherYear->id,
            'level_id' => $level6e->id,
            'section' => 'X',
            'name' => '6ème X',
        ]);

        // Without academic_year_id, should only show active year classes
        $response = $this->authGetJson('/api/admin/classes');

        $response->assertOk();
        $response->assertJsonCount(6, 'data'); // Only the 6 seeded classes
    }

    #[Test]
    public function it_filters_by_specific_academic_year(): void
    {
        $otherYear = AcademicYear::factory()->create(['name' => '2024-2025']);
        $level6e = Level::on('tenant')->where('code', '6E')->first();
        Classe::on('tenant')->create([
            'academic_year_id' => $otherYear->id,
            'level_id' => $level6e->id,
            'section' => 'X',
            'name' => '6ème X',
        ]);

        $response = $this->authGetJson("/api/admin/classes?academic_year_id={$otherYear->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    // =============================================
    // Search
    // =============================================

    #[Test]
    public function it_searches_classes_by_name(): void
    {
        $response = $this->authGetJson('/api/admin/classes?search=Tle');

        $response->assertOk();
        $response->assertJsonCount(2, 'data'); // Tle A1, Tle C1
    }

    // =============================================
    // Stats endpoint
    // =============================================

    #[Test]
    public function it_returns_stats_for_active_year(): void
    {
        $response = $this->authGetJson('/api/admin/classes/stats');

        $response->assertOk();
        $response->assertJsonPath('data.total_classes', 6);
    }

    #[Test]
    public function it_returns_stats_for_specific_year(): void
    {
        $response = $this->authGetJson("/api/admin/classes/stats?academic_year_id={$this->activeYear->id}");

        $response->assertOk();
        $response->assertJsonPath('data.total_classes', 6);
        $response->assertJsonStructure([
            'data' => [
                'total_classes',
                'classes_by_cycle',
                'classes_by_level',
            ],
        ]);
    }

    #[Test]
    public function it_returns_correct_stats_by_cycle(): void
    {
        $response = $this->authGetJson("/api/admin/classes/stats?academic_year_id={$this->activeYear->id}");

        $response->assertOk();
        $byCycle = collect($response->json('data.classes_by_cycle'));
        $collegeStats = $byCycle->firstWhere('code', 'COL');
        $lyceeStats = $byCycle->firstWhere('code', 'LYC');

        $this->assertEquals(3, $collegeStats['count']);
        $this->assertEquals(3, $lyceeStats['count']);
    }

    #[Test]
    public function it_returns_correct_stats_by_level(): void
    {
        $response = $this->authGetJson("/api/admin/classes/stats?academic_year_id={$this->activeYear->id}");

        $response->assertOk();
        $byLevel = collect($response->json('data.classes_by_level'));

        $level6e = $byLevel->firstWhere('code', '6E');
        $levelTle = $byLevel->firstWhere('code', 'TLE');

        $this->assertEquals(2, $level6e['count']); // 6ème A, 6ème B
        $this->assertEquals(2, $levelTle['count']); // Tle A1, Tle C1
    }

    #[Test]
    public function it_returns_zero_stats_for_empty_year(): void
    {
        $emptyYear = AcademicYear::factory()->create(['name' => '2023-2024']);

        $response = $this->authGetJson("/api/admin/classes/stats?academic_year_id={$emptyYear->id}");

        $response->assertOk();
        $response->assertJsonPath('data.total_classes', 0);
    }
}
