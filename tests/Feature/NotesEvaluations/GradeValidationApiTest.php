<?php

namespace Tests\Feature\NotesEvaluations;

use Illuminate\Support\Facades\Artisan;
use Modules\NotesEvaluations\Entities\GradeValidation;
use Modules\UsersGuard\Entities\TenantUser as User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class GradeValidationApiTest extends TestCase
{
    use InteractsWithTenancy {
        runMigrationsOnce as runBaseMigrationsOnce;
    }

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->seedRolesAndPermissions();
        $this->user = User::factory()->create();
        $this->assignRole($this->user, 'Administrator');
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    /**
     * Run the shared tenant migrations, then the NotesEvaluations tables (not part of the shared set).
     */
    protected function runMigrationsOnce(): void
    {
        if (self::$migrationsRan) {
            return;
        }

        $this->runBaseMigrationsOnce();

        Artisan::call('migrate', [
            '--database' => 'mysql',
            '--path' => base_path('Modules/NotesEvaluations/Database/Migrations/tenant'),
            '--realpath' => true,
            '--force' => true,
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
    public function it_can_list_grade_validations(): void
    {
        GradeValidation::factory()->count(3)->create();

        $response = $this->authGetJson('/api/admin/grade-validations');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    #[Test]
    public function it_can_show_a_grade_validation(): void
    {
        $validation = GradeValidation::factory()->create();

        $response = $this->authGetJson("/api/admin/grade-validations/{$validation->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $validation->id)
            ->assertJsonPath('data.status', 'Pending');
    }

    #[Test]
    public function it_can_approve_a_pending_validation(): void
    {
        $validation = GradeValidation::factory()->create(['status' => 'Pending']);

        $response = $this->authPostJson("/api/admin/grade-validations/{$validation->id}/validate", [
            'notes' => 'Looks good',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'Approved');

        $this->assertDatabaseHas('grade_validations', [
            'id' => $validation->id,
            'status' => 'Approved',
            'validated_by' => $this->user->id,
        ], 'tenant');
    }

    #[Test]
    public function it_cannot_approve_an_already_approved_validation(): void
    {
        $validation = GradeValidation::factory()->approved()->create();

        $response = $this->authPostJson("/api/admin/grade-validations/{$validation->id}/validate", [
            'notes' => 'Try again',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cette demande ne peut plus être validée.');
    }

    #[Test]
    public function it_can_reject_a_pending_validation(): void
    {
        $validation = GradeValidation::factory()->create(['status' => 'Pending']);

        $response = $this->authPostJson("/api/admin/grade-validations/{$validation->id}/reject", [
            'reason' => 'Grades are inconsistent',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'Rejected');

        $this->assertDatabaseHas('grade_validations', [
            'id' => $validation->id,
            'status' => 'Rejected',
        ], 'tenant');
    }

    #[Test]
    public function it_requires_reason_to_reject(): void
    {
        $validation = GradeValidation::factory()->create(['status' => 'Pending']);

        $response = $this->authPostJson("/api/admin/grade-validations/{$validation->id}/reject", []);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_can_publish_an_approved_validation(): void
    {
        $validation = GradeValidation::factory()->approved()->create();

        $response = $this->authPostJson("/api/admin/grade-validations/{$validation->id}/publish");

        $response->assertOk()
            ->assertJsonPath('data.status', 'Published');
    }

    #[Test]
    public function it_cannot_publish_a_pending_validation(): void
    {
        $validation = GradeValidation::factory()->create(['status' => 'Pending']);

        $response = $this->authPostJson("/api/admin/grade-validations/{$validation->id}/publish");

        $response->assertStatus(422);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $response = $this->getJson('/api/admin/grade-validations');

        $response->assertStatus(401);
    }
}
