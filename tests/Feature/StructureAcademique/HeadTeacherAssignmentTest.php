<?php

namespace Tests\Feature\StructureAcademique;

use Modules\StructureAcademique\Database\Seeders\CycleAndLevelSeeder;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class HeadTeacherAssignmentTest extends TestCase
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

        (new CycleAndLevelSeeder)->run();
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

    // =============================================
    // PP Assignment
    // =============================================

    #[Test]
    public function it_can_assign_head_teacher_on_create(): void
    {
        $teacher = User::factory()->create();
        $level6e = Level::on('tenant')->where('code', '6E')->first();

        $response = $this->authPostJson('/api/admin/classes', [
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'head_teacher_id' => $teacher->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.head_teacher.id', $teacher->id);
    }

    #[Test]
    public function it_blocks_same_teacher_as_pp_for_two_classes_same_year(): void
    {
        $teacher = User::factory()->create();
        $level6e = Level::on('tenant')->where('code', '6E')->first();

        // Assign teacher as PP for first class
        $this->authPostJson('/api/admin/classes', [
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'head_teacher_id' => $teacher->id,
        ])->assertStatus(201);

        // Try to assign same teacher as PP for second class
        $response = $this->authPostJson('/api/admin/classes', [
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'B',
            'head_teacher_id' => $teacher->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('head_teacher_id');
    }

    #[Test]
    public function it_allows_same_teacher_as_pp_in_different_years(): void
    {
        $teacher = User::factory()->create();
        $level6e = Level::on('tenant')->where('code', '6E')->first();
        $otherYear = AcademicYear::factory()->create(['name' => '2024-2025']);

        // Assign in year 1
        $this->authPostJson('/api/admin/classes', [
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'head_teacher_id' => $teacher->id,
        ])->assertStatus(201);

        // Assign in year 2
        $response = $this->authPostJson('/api/admin/classes', [
            'academic_year_id' => $otherYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'head_teacher_id' => $teacher->id,
        ]);

        $response->assertStatus(201);
    }

    #[Test]
    public function it_can_change_head_teacher(): void
    {
        $teacher1 = User::factory()->create();
        $teacher2 = User::factory()->create();
        $level6e = Level::on('tenant')->where('code', '6E')->first();

        $classe = Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'name' => '6ème A',
            'head_teacher_id' => $teacher1->id,
        ]);

        $response = $this->authPutJson("/api/admin/classes/{$classe->id}", [
            'head_teacher_id' => $teacher2->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.head_teacher.id', $teacher2->id);
    }

    #[Test]
    public function it_blocks_update_when_teacher_already_pp_of_another_class(): void
    {
        $teacher = User::factory()->create();
        $level6e = Level::on('tenant')->where('code', '6E')->first();

        // Create class A with this teacher
        Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'name' => '6ème A',
            'head_teacher_id' => $teacher->id,
        ]);

        // Create class B without PP
        $classeB = Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'B',
            'name' => '6ème B',
        ]);

        // Try to assign same teacher to class B
        $response = $this->authPutJson("/api/admin/classes/{$classeB->id}", [
            'head_teacher_id' => $teacher->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('head_teacher_id');
    }

    #[Test]
    public function it_allows_reassigning_same_teacher_to_same_class(): void
    {
        $teacher = User::factory()->create();
        $level6e = Level::on('tenant')->where('code', '6E')->first();

        $classe = Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'name' => '6ème A',
            'head_teacher_id' => $teacher->id,
        ]);

        // Update same class with same teacher (should pass)
        $response = $this->authPutJson("/api/admin/classes/{$classe->id}", [
            'head_teacher_id' => $teacher->id,
        ]);

        $response->assertOk();
    }

    // =============================================
    // Available Head Teachers endpoint
    // =============================================

    #[Test]
    public function it_lists_available_head_teachers(): void
    {
        $teacher1 = User::factory()->create();
        $teacher2 = User::factory()->create();
        $teacher3 = User::factory()->create();
        $level6e = Level::on('tenant')->where('code', '6E')->first();

        // Assign teacher1 as PP
        Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'name' => '6ème A',
            'head_teacher_id' => $teacher1->id,
        ]);

        $response = $this->authGetJson("/api/admin/classes/available-head-teachers?academic_year_id={$this->activeYear->id}");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        // teacher1 should NOT be in the list (already PP)
        $this->assertNotContains($teacher1->id, $ids);
        // teacher2, teacher3, and the test user should be available
        $this->assertContains($teacher2->id, $ids);
        $this->assertContains($teacher3->id, $ids);
    }

    #[Test]
    public function it_excludes_current_class_teacher_from_used_list(): void
    {
        $teacher = User::factory()->create();
        $level6e = Level::on('tenant')->where('code', '6E')->first();

        $classe = Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'name' => '6ème A',
            'head_teacher_id' => $teacher->id,
        ]);

        // When editing class A, teacher should still appear as available
        $response = $this->authGetJson("/api/admin/classes/available-head-teachers?academic_year_id={$this->activeYear->id}&exclude_class_id={$classe->id}");

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($teacher->id, $ids);
    }

    // =============================================
    // Classes without head teacher
    // =============================================

    #[Test]
    public function it_lists_classes_without_head_teacher(): void
    {
        $teacher = User::factory()->create();
        $level6e = Level::on('tenant')->where('code', '6E')->first();

        // Class with PP
        Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'name' => '6ème A',
            'head_teacher_id' => $teacher->id,
        ]);

        // Class without PP
        Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'B',
            'name' => '6ème B',
            'head_teacher_id' => null,
        ]);

        $response = $this->authGetJson("/api/admin/classes/without-head-teacher?academic_year_id={$this->activeYear->id}");

        $response->assertOk();
        $response->assertJsonPath('count', 1);
        $this->assertEquals('6ème B', $response->json('data.0.name'));
    }

    #[Test]
    public function it_returns_empty_when_all_classes_have_pp(): void
    {
        $teacher = User::factory()->create();
        $level6e = Level::on('tenant')->where('code', '6E')->first();

        Classe::on('tenant')->create([
            'academic_year_id' => $this->activeYear->id,
            'level_id' => $level6e->id,
            'section' => 'A',
            'name' => '6ème A',
            'head_teacher_id' => $teacher->id,
        ]);

        $response = $this->authGetJson("/api/admin/classes/without-head-teacher?academic_year_id={$this->activeYear->id}");

        $response->assertOk();
        $response->assertJsonPath('count', 0);
    }
}
