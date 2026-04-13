<?php

namespace Tests\Feature\StructureAcademique;

use Modules\StructureAcademique\Database\Seeders\SeriesSeeder;
use Modules\StructureAcademique\Entities\Series;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class SeriesApiTest extends TestCase
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

    private function seedSeries(): void
    {
        (new SeriesSeeder)->run();
    }

    // =============================================
    // List
    // =============================================

    #[Test]
    public function it_can_list_series(): void
    {
        $this->seedSeries();

        $response = $this->authGetJson('/api/admin/series');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_returns_series_ordered_by_code(): void
    {
        $this->seedSeries();

        $response = $this->authGetJson('/api/admin/series');

        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertEquals(['A', 'C', 'D'], $codes);
    }

    // =============================================
    // Create
    // =============================================

    #[Test]
    public function it_can_create_a_series(): void
    {
        $response = $this->authPostJson('/api/admin/series', [
            'code' => 'E',
            'name' => 'Économie',
            'description' => 'Sciences Économiques',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.code', 'E');
        $response->assertJsonPath('data.name', 'Économie');
    }

    #[Test]
    public function it_forces_code_to_uppercase(): void
    {
        $response = $this->authPostJson('/api/admin/series', [
            'code' => 'abc',
            'name' => 'Test série',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.code', 'ABC');
    }

    #[Test]
    public function it_requires_unique_code(): void
    {
        Series::factory()->create(['code' => 'A']);

        $response = $this->authPostJson('/api/admin/series', [
            'code' => 'A',
            'name' => 'Autre série',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('code');
    }

    #[Test]
    public function it_validates_required_fields_on_create(): void
    {
        $response = $this->authPostJson('/api/admin/series', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code', 'name']);
    }

    #[Test]
    public function it_validates_code_max_length(): void
    {
        $response = $this->authPostJson('/api/admin/series', [
            'code' => 'ABCDEFGHIJK', // 11 chars > max 10
            'name' => 'Test',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('code');
    }

    // =============================================
    // Show
    // =============================================

    #[Test]
    public function it_can_show_a_series(): void
    {
        $series = Series::factory()->create(['code' => 'A', 'name' => 'Littéraire']);

        $response = $this->authGetJson("/api/admin/series/{$series->id}");

        $response->assertOk();
        $response->assertJsonPath('data.code', 'A');
        $response->assertJsonPath('data.name', 'Littéraire');
    }

    // =============================================
    // Update
    // =============================================

    #[Test]
    public function it_can_update_series_name_and_description(): void
    {
        $series = Series::factory()->create(['code' => 'A', 'name' => 'Littéraire']);

        $response = $this->authPutJson("/api/admin/series/{$series->id}", [
            'name' => 'Série Littéraire',
            'description' => 'Nouvelle description',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Série Littéraire');
        $response->assertJsonPath('data.description', 'Nouvelle description');
        $response->assertJsonPath('data.code', 'A'); // code unchanged
    }

    #[Test]
    public function it_can_deactivate_a_series(): void
    {
        $series = Series::factory()->create(['code' => 'A', 'is_active' => true]);

        $response = $this->authPutJson("/api/admin/series/{$series->id}", [
            'is_active' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_active', false);
    }

    #[Test]
    public function it_can_reactivate_a_series(): void
    {
        $series = Series::factory()->inactive()->create(['code' => 'A']);

        $response = $this->authPutJson("/api/admin/series/{$series->id}", [
            'is_active' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_active', true);
    }

    // =============================================
    // Destroy (forbidden)
    // =============================================

    #[Test]
    public function it_blocks_deletion_and_returns_403(): void
    {
        $series = Series::factory()->create(['code' => 'A']);

        $response = $this->authDeleteJson("/api/admin/series/{$series->id}");

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'La suppression n\'est pas autorisée, utilisez la désactivation.']);

        // Verify not deleted
        $this->assertNotNull(Series::on('tenant')->find($series->id));
    }

    // =============================================
    // Seeder
    // =============================================

    #[Test]
    public function seeder_creates_correct_series(): void
    {
        $this->seedSeries();

        $this->assertEquals(3, Series::on('tenant')->count());

        $codes = Series::on('tenant')->pluck('code')->sort()->values()->all();
        $this->assertEquals(['A', 'C', 'D'], $codes);
    }

    #[Test]
    public function seeder_is_idempotent(): void
    {
        $this->seedSeries();
        $this->seedSeries();

        $this->assertEquals(3, Series::on('tenant')->count());
    }

    // =============================================
    // Auth
    // =============================================

    #[Test]
    public function it_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin/series');
        $response->assertStatus(401);
    }
}
