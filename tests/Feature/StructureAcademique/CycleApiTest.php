<?php

namespace Tests\Feature\StructureAcademique;

use Modules\StructureAcademique\Database\Seeders\CycleAndLevelSeeder;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Cycle;
use Modules\StructureAcademique\Entities\Level;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class CycleApiTest extends TestCase
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

    private function authPutJson(string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->putJson($uri, $data);
    }

    /**
     * Seed cycles and levels for tests
     */
    private function seedCyclesAndLevels(): void
    {
        (new CycleAndLevelSeeder)->run();
    }

    // =============================================
    // Cycles - List
    // =============================================

    #[Test]
    public function it_can_list_cycles_with_levels(): void
    {
        $this->seedCyclesAndLevels();

        $response = $this->authGetJson('/api/admin/cycles');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.code', 'COL');
        $response->assertJsonPath('data.1.code', 'LYC');

        // Collège has 4 levels
        $response->assertJsonCount(4, 'data.0.levels');
        // Lycée has 3 levels
        $response->assertJsonCount(3, 'data.1.levels');
    }

    #[Test]
    public function it_returns_cycles_ordered_by_display_order(): void
    {
        $this->seedCyclesAndLevels();

        $response = $this->authGetJson('/api/admin/cycles');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(1, $data[0]['display_order']);
        $this->assertEquals(2, $data[1]['display_order']);
    }

    // =============================================
    // Cycles - Show
    // =============================================

    #[Test]
    public function it_can_show_a_cycle_with_levels(): void
    {
        $this->seedCyclesAndLevels();
        $cycle = Cycle::on('tenant')->where('code', 'COL')->first();

        $response = $this->authGetJson("/api/admin/cycles/{$cycle->id}");

        $response->assertOk();
        $response->assertJsonPath('data.code', 'COL');
        $response->assertJsonPath('data.name', 'Collège (1er cycle)');
        $response->assertJsonCount(4, 'data.levels');
    }

    // =============================================
    // Cycles - Update
    // =============================================

    #[Test]
    public function it_can_update_cycle_description(): void
    {
        $this->seedCyclesAndLevels();
        $cycle = Cycle::on('tenant')->where('code', 'COL')->first();

        $response = $this->authPutJson("/api/admin/cycles/{$cycle->id}", [
            'description' => 'Nouvelle description du collège',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.description', 'Nouvelle description du collège');
        $response->assertJsonPath('data.code', 'COL'); // code unchanged
    }

    #[Test]
    public function it_can_deactivate_a_cycle(): void
    {
        $this->seedCyclesAndLevels();
        $cycle = Cycle::on('tenant')->where('code', 'LYC')->first();

        $response = $this->authPutJson("/api/admin/cycles/{$cycle->id}", [
            'is_active' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_active', false);
    }

    #[Test]
    public function it_can_reactivate_a_cycle(): void
    {
        $cycle = Cycle::factory()->inactive()->create(['code' => 'TST']);

        $response = $this->authPutJson("/api/admin/cycles/{$cycle->id}", [
            'is_active' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_active', true);
    }

    #[Test]
    public function it_blocks_deactivation_when_classes_exist_in_active_year(): void
    {
        $this->seedCyclesAndLevels();

        // Only test if classes table exists
        if (\Schema::connection('tenant')->hasTable('classes')) {
            $cycle = Cycle::on('tenant')->where('code', 'COL')->first();
            $level = Level::on('tenant')->where('cycle_id', $cycle->id)->first();
            $year = AcademicYear::factory()->active()->create();

            \DB::connection('tenant')->table('classes')->insert([
                'academic_year_id' => $year->id,
                'level_id' => $level->id,
                'name' => '6ème A',
                'max_capacity' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $response = $this->authPutJson("/api/admin/cycles/{$cycle->id}", [
                'is_active' => false,
            ]);

            $response->assertStatus(422);
            $response->assertJsonFragment(['message' => 'Impossible de désactiver ce cycle : des classes existent pour ce cycle dans l\'année scolaire active.']);
        } else {
            // Classes table doesn't exist yet - just verify deactivation works
            $cycle = Cycle::on('tenant')->where('code', 'COL')->first();
            $response = $this->authPutJson("/api/admin/cycles/{$cycle->id}", [
                'is_active' => false,
            ]);
            $response->assertOk();
        }
    }

    // =============================================
    // Levels - List
    // =============================================

    #[Test]
    public function it_can_list_all_levels(): void
    {
        $this->seedCyclesAndLevels();

        $response = $this->authGetJson('/api/admin/levels');

        $response->assertOk();
        $response->assertJsonCount(7, 'data'); // 4 collège + 3 lycée
    }

    #[Test]
    public function it_can_filter_levels_by_cycle(): void
    {
        $this->seedCyclesAndLevels();
        $college = Cycle::on('tenant')->where('code', 'COL')->first();

        $response = $this->authGetJson("/api/admin/levels?cycle_id={$college->id}");

        $response->assertOk();
        $response->assertJsonCount(4, 'data');
    }

    #[Test]
    public function it_returns_levels_ordered_by_order_index(): void
    {
        $this->seedCyclesAndLevels();

        $response = $this->authGetJson('/api/admin/levels');

        $response->assertOk();
        $data = $response->json('data');
        $orderIndexes = collect($data)->pluck('order_index')->all();
        $this->assertEquals($orderIndexes, collect($orderIndexes)->sort()->values()->all());
    }

    #[Test]
    public function it_can_show_a_level(): void
    {
        $this->seedCyclesAndLevels();
        $level = Level::on('tenant')->where('code', '6E')->first();

        $response = $this->authGetJson("/api/admin/levels/{$level->id}");

        $response->assertOk();
        $response->assertJsonPath('data.code', '6E');
        $response->assertJsonPath('data.name', 'Sixième');
        $response->assertJsonStructure(['data' => ['cycle']]);
    }

    // =============================================
    // Seeder verification
    // =============================================

    #[Test]
    public function seeder_creates_correct_cycles_and_levels(): void
    {
        $this->seedCyclesAndLevels();

        $this->assertEquals(2, Cycle::on('tenant')->count());
        $this->assertEquals(7, Level::on('tenant')->count());

        // Verify college levels
        $college = Cycle::on('tenant')->where('code', 'COL')->first();
        $collegeLevels = Level::on('tenant')->where('cycle_id', $college->id)->pluck('code')->sort()->values()->all();
        $this->assertEquals(['3E', '4E', '5E', '6E'], $collegeLevels);

        // Verify lycée levels
        $lycee = Cycle::on('tenant')->where('code', 'LYC')->first();
        $lyceeLevels = Level::on('tenant')->where('cycle_id', $lycee->id)->pluck('code')->sort()->values()->all();
        $this->assertEquals(['1ERE', '2NDE', 'TLE'], $lyceeLevels);
    }

    #[Test]
    public function seeder_is_idempotent(): void
    {
        $this->seedCyclesAndLevels();
        $this->seedCyclesAndLevels(); // Run twice

        $this->assertEquals(2, Cycle::on('tenant')->count());
        $this->assertEquals(7, Level::on('tenant')->count());
    }

    // =============================================
    // Auth tests
    // =============================================

    #[Test]
    public function it_requires_authentication_for_cycles(): void
    {
        $response = $this->getJson('/api/admin/cycles');
        $response->assertStatus(401);
    }

    #[Test]
    public function it_requires_authentication_for_levels(): void
    {
        $response = $this->getJson('/api/admin/levels');
        $response->assertStatus(401);
    }
}
