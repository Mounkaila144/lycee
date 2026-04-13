<?php

namespace Tests\Feature\StructureAcademique;

use Modules\StructureAcademique\Database\Seeders\CycleAndLevelSeeder;
use Modules\StructureAcademique\Database\Seeders\SubjectSeeder;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Entities\Subject;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class SubjectApiTest extends TestCase
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

    private function seedSubjects(): void
    {
        (new SubjectSeeder)->run();
    }

    // =============================================
    // List
    // =============================================

    #[Test]
    public function it_can_list_subjects(): void
    {
        Subject::factory()->count(3)->create();

        $response = $this->authGetJson('/api/admin/subjects');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_paginates_subjects(): void
    {
        Subject::factory()->count(20)->create();

        $response = $this->authGetJson('/api/admin/subjects?per_page=10');

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.last_page', 2);
    }

    #[Test]
    public function it_can_search_subjects_by_code(): void
    {
        Subject::factory()->create(['code' => 'MATH', 'name' => 'Mathématiques']);
        Subject::factory()->create(['code' => 'FRAN', 'name' => 'Français']);

        $response = $this->authGetJson('/api/admin/subjects?search=MATH');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.code', 'MATH');
    }

    #[Test]
    public function it_can_search_subjects_by_name(): void
    {
        Subject::factory()->create(['code' => 'MATH', 'name' => 'Mathématiques']);
        Subject::factory()->create(['code' => 'FRAN', 'name' => 'Français']);

        $response = $this->authGetJson('/api/admin/subjects?search=Math');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.code', 'MATH');
    }

    #[Test]
    public function it_can_filter_subjects_by_category(): void
    {
        Subject::factory()->create(['code' => 'MATH', 'category' => 'sciences']);
        Subject::factory()->create(['code' => 'FRAN', 'category' => 'lettres']);
        Subject::factory()->create(['code' => 'PHYS', 'category' => 'sciences']);

        $response = $this->authGetJson('/api/admin/subjects?category=sciences');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_can_filter_subjects_by_active_status(): void
    {
        Subject::factory()->create(['code' => 'MATH', 'is_active' => true]);
        Subject::factory()->inactive()->create(['code' => 'FRAN']);

        $response = $this->authGetJson('/api/admin/subjects?is_active=true');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.code', 'MATH');
    }

    #[Test]
    public function it_returns_subjects_ordered_by_code(): void
    {
        Subject::factory()->create(['code' => 'PHYS']);
        Subject::factory()->create(['code' => 'FRAN']);
        Subject::factory()->create(['code' => 'MATH']);

        $response = $this->authGetJson('/api/admin/subjects');

        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertEquals(['FRAN', 'MATH', 'PHYS'], $codes);
    }

    // =============================================
    // Create
    // =============================================

    #[Test]
    public function it_can_create_a_subject(): void
    {
        $response = $this->authPostJson('/api/admin/subjects', [
            'code' => 'MATH',
            'name' => 'Mathématiques',
            'short_name' => 'Maths',
            'category' => 'sciences',
            'description' => 'La science des nombres',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.code', 'MATH');
        $response->assertJsonPath('data.name', 'Mathématiques');
        $response->assertJsonPath('data.short_name', 'Maths');
        $response->assertJsonPath('data.category', 'sciences');
    }

    #[Test]
    public function it_forces_code_to_uppercase(): void
    {
        $response = $this->authPostJson('/api/admin/subjects', [
            'code' => 'math',
            'name' => 'Mathématiques',
            'short_name' => 'Maths',
            'category' => 'sciences',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.code', 'MATH');
    }

    #[Test]
    public function it_requires_unique_code(): void
    {
        Subject::factory()->create(['code' => 'MATH']);

        $response = $this->authPostJson('/api/admin/subjects', [
            'code' => 'MATH',
            'name' => 'Autre matière',
            'short_name' => 'Autre',
            'category' => 'sciences',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('code');
    }

    #[Test]
    public function it_validates_required_fields_on_create(): void
    {
        $response = $this->authPostJson('/api/admin/subjects', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code', 'name', 'short_name', 'category']);
    }

    #[Test]
    public function it_validates_category_enum_value(): void
    {
        $response = $this->authPostJson('/api/admin/subjects', [
            'code' => 'TEST',
            'name' => 'Test',
            'short_name' => 'T',
            'category' => 'invalid_category',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('category');
    }

    // =============================================
    // Show
    // =============================================

    #[Test]
    public function it_can_show_a_subject(): void
    {
        $subject = Subject::factory()->create([
            'code' => 'MATH',
            'name' => 'Mathématiques',
            'short_name' => 'Maths',
            'category' => 'sciences',
        ]);

        $response = $this->authGetJson("/api/admin/subjects/{$subject->id}");

        $response->assertOk();
        $response->assertJsonPath('data.code', 'MATH');
        $response->assertJsonPath('data.name', 'Mathématiques');
        $response->assertJsonPath('data.short_name', 'Maths');
    }

    #[Test]
    public function it_returns_404_for_nonexistent_subject(): void
    {
        $response = $this->authGetJson('/api/admin/subjects/999');

        $response->assertNotFound();
    }

    // =============================================
    // Update
    // =============================================

    #[Test]
    public function it_can_update_a_subject(): void
    {
        $subject = Subject::factory()->create(['code' => 'MATH', 'name' => 'Mathématiques']);

        $response = $this->authPutJson("/api/admin/subjects/{$subject->id}", [
            'name' => 'Mathématiques Avancées',
            'short_name' => 'Maths Adv',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Mathématiques Avancées');
        $response->assertJsonPath('data.short_name', 'Maths Adv');
    }

    #[Test]
    public function it_can_deactivate_a_subject(): void
    {
        $subject = Subject::factory()->create(['code' => 'MATH', 'is_active' => true]);

        $response = $this->authPutJson("/api/admin/subjects/{$subject->id}", [
            'is_active' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_active', false);
    }

    #[Test]
    public function it_can_reactivate_a_subject(): void
    {
        $subject = Subject::factory()->inactive()->create(['code' => 'MATH']);

        $response = $this->authPutJson("/api/admin/subjects/{$subject->id}", [
            'is_active' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_active', true);
    }

    // =============================================
    // Destroy
    // =============================================

    #[Test]
    public function it_can_delete_a_subject_without_dependencies(): void
    {
        $subject = Subject::factory()->create(['code' => 'MATH']);

        $response = $this->authDeleteJson("/api/admin/subjects/{$subject->id}");

        $response->assertOk();
        $this->assertSoftDeleted('subjects', ['id' => $subject->id], connection: 'tenant');
    }

    #[Test]
    public function it_blocks_deletion_when_coefficients_exist(): void
    {
        (new CycleAndLevelSeeder)->run();
        $level = Level::on('tenant')->where('code', '6E')->first();
        $subject = Subject::factory()->create(['code' => 'MATH']);

        \DB::connection('tenant')->table('subject_class_coefficients')->insert([
            'subject_id' => $subject->id,
            'level_id' => $level->id,
            'coefficient' => 4.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->authDeleteJson("/api/admin/subjects/{$subject->id}");

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Impossible de supprimer cette matière : des coefficients, notes ou affectations enseignants existent.']);
        $this->assertNotNull(Subject::on('tenant')->find($subject->id));
    }

    // =============================================
    // Seeder
    // =============================================

    #[Test]
    public function seeder_creates_correct_subjects(): void
    {
        $this->seedSubjects();

        $this->assertEquals(13, Subject::on('tenant')->count());

        $codes = Subject::on('tenant')->pluck('code')->sort()->values()->all();
        $this->assertContains('MATH', $codes);
        $this->assertContains('FRAN', $codes);
        $this->assertContains('PHYS', $codes);
    }

    #[Test]
    public function seeder_is_idempotent(): void
    {
        $this->seedSubjects();
        $this->seedSubjects();

        $this->assertEquals(13, Subject::on('tenant')->count());
    }

    // =============================================
    // Auth
    // =============================================

    #[Test]
    public function it_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin/subjects');
        $response->assertStatus(401);
    }
}
