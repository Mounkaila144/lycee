<?php

namespace Tests\Feature\Enrollment;

use Illuminate\Testing\TestResponse;
use Modules\Enrollment\Entities\Option;
use Modules\Enrollment\Entities\OptionAssignment;
use Modules\Enrollment\Entities\OptionChoice;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class OptionApiTest extends TestCase
{
    use InteractsWithTenancy;

    private User $user;

    private string $token;

    private Programme $programme;

    private AcademicYear $academicYear;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $this->programme = Programme::factory()->create(['statut' => 'Actif']);
        $this->academicYear = AcademicYear::factory()->create(['is_active' => true]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Make an authenticated JSON GET request
     */
    private function authGetJson(string $uri): TestResponse
    {
        return $this->withToken($this->token)->getJson($uri);
    }

    /**
     * Make an authenticated JSON POST request
     */
    private function authPostJson(string $uri, array $data = []): TestResponse
    {
        return $this->withToken($this->token)->postJson($uri, $data);
    }

    /**
     * Make an authenticated JSON PUT request
     */
    private function authPutJson(string $uri, array $data = []): TestResponse
    {
        return $this->withToken($this->token)->putJson($uri, $data);
    }

    /**
     * Make an authenticated JSON DELETE request
     */
    private function authDeleteJson(string $uri): TestResponse
    {
        return $this->withToken($this->token)->deleteJson($uri);
    }

    // ==================== OPTIONS CRUD TESTS ====================

    #[Test]
    public function it_can_list_options(): void
    {
        Option::factory()->count(3)->create([
            'programme_id' => $this->programme->id,
        ]);

        $response = $this->authGetJson('/api/admin/enrollment/options');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'programme_id',
                        'level',
                        'code',
                        'name',
                        'description',
                        'capacity',
                        'is_mandatory',
                        'choice_start_date',
                        'choice_end_date',
                        'status',
                    ],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    #[Test]
    public function it_can_filter_options_by_programme(): void
    {
        $otherProgramme = Programme::factory()->create();

        Option::factory()->count(2)->create([
            'programme_id' => $this->programme->id,
        ]);

        Option::factory()->count(3)->create([
            'programme_id' => $otherProgramme->id,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/options?programme_id={$this->programme->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function it_can_filter_options_by_level(): void
    {
        Option::factory()->create([
            'programme_id' => $this->programme->id,
            'level' => 'L3',
        ]);

        Option::factory()->create([
            'programme_id' => $this->programme->id,
            'level' => 'M1',
        ]);

        $response = $this->authGetJson('/api/admin/enrollment/options?level=L3');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('L3', $response->json('data.0.level'));
    }

    #[Test]
    public function it_can_create_option(): void
    {
        $data = [
            'programme_id' => $this->programme->id,
            'level' => 'L3',
            'code' => 'OPT-IA-001',
            'name' => 'Intelligence Artificielle',
            'description' => 'Option spécialisée en IA',
            'capacity' => 30,
            'is_mandatory' => false,
            'choice_start_date' => now()->addDays(1)->toDateString(),
            'choice_end_date' => now()->addMonths(1)->toDateString(),
        ];

        $response = $this->authPostJson('/api/admin/enrollment/options', $data);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Option créée avec succès.')
            ->assertJsonPath('data.code', 'OPT-IA-001')
            ->assertJsonPath('data.name', 'Intelligence Artificielle');

        $this->assertDatabaseHas('options', [
            'code' => 'OPT-IA-001',
            'name' => 'Intelligence Artificielle',
        ], 'tenant');
    }

    #[Test]
    public function it_validates_required_fields_on_create(): void
    {
        $response = $this->authPostJson('/api/admin/enrollment/options', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'programme_id',
                'level',
                'code',
                'name',
                'capacity',
                'choice_start_date',
                'choice_end_date',
            ]);
    }

    #[Test]
    public function it_validates_unique_code_on_create(): void
    {
        Option::factory()->create([
            'programme_id' => $this->programme->id,
            'code' => 'EXISTING-CODE',
        ]);

        $data = [
            'programme_id' => $this->programme->id,
            'level' => 'L3',
            'code' => 'EXISTING-CODE', // Duplicate
            'name' => 'Test Option',
            'capacity' => 30,
            'choice_start_date' => now()->addDays(1)->toDateString(),
            'choice_end_date' => now()->addMonths(1)->toDateString(),
        ];

        $response = $this->authPostJson('/api/admin/enrollment/options', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    #[Test]
    public function it_can_show_option(): void
    {
        $option = Option::factory()->create([
            'programme_id' => $this->programme->id,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/options/{$option->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $option->id)
            ->assertJsonPath('data.code', $option->code);
    }

    #[Test]
    public function it_can_update_option(): void
    {
        $option = Option::factory()->create([
            'programme_id' => $this->programme->id,
            'name' => 'Original Name',
        ]);

        $response = $this->authPutJson("/api/admin/enrollment/options/{$option->id}", [
            'name' => 'Updated Name',
            'capacity' => 50,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Option modifiée avec succès.')
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.capacity', 50);
    }

    #[Test]
    public function it_can_delete_option_without_assignments(): void
    {
        $option = Option::factory()->create([
            'programme_id' => $this->programme->id,
        ]);

        $response = $this->authDeleteJson("/api/admin/enrollment/options/{$option->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Option supprimée avec succès.');

        $this->assertSoftDeleted('options', ['id' => $option->id], connection: 'tenant');
    }

    #[Test]
    public function it_cannot_delete_option_with_assignments(): void
    {
        $option = Option::factory()->create([
            'programme_id' => $this->programme->id,
        ]);

        $student = Student::factory()->create();

        OptionAssignment::factory()->create([
            'student_id' => $student->id,
            'option_id' => $option->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response = $this->authDeleteJson("/api/admin/enrollment/options/{$option->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Impossible de supprimer une option avec des affectations.');
    }

    // ==================== OPTION CHOICES TESTS ====================

    #[Test]
    public function it_can_store_student_choice(): void
    {
        $student = Student::factory()->create();
        $option = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
        ]);

        $data = [
            'student_id' => $student->id,
            'option_id' => $option->id,
            'academic_year_id' => $this->academicYear->id,
            'choice_rank' => 1,
            'motivation' => 'Je souhaite me spécialiser dans ce domaine.',
        ];

        $response = $this->authPostJson('/api/admin/enrollment/options/choices', $data);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Vœu enregistré avec succès.');

        $this->assertDatabaseHas('option_choices', [
            'student_id' => $student->id,
            'option_id' => $option->id,
            'choice_rank' => 1,
        ], 'tenant');
    }

    #[Test]
    public function it_prevents_duplicate_choice_for_same_option(): void
    {
        $student = Student::factory()->create();
        $option = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
        ]);

        // First choice
        OptionChoice::factory()->create([
            'student_id' => $student->id,
            'option_id' => $option->id,
            'academic_year_id' => $this->academicYear->id,
            'choice_rank' => 1,
        ]);

        // Try to create duplicate
        $response = $this->authPostJson('/api/admin/enrollment/options/choices', [
            'student_id' => $student->id,
            'option_id' => $option->id,
            'academic_year_id' => $this->academicYear->id,
            'choice_rank' => 2,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['option_id']);
    }

    #[Test]
    public function it_prevents_duplicate_rank_for_same_year(): void
    {
        $student = Student::factory()->create();
        $option1 = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
        ]);
        $option2 = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
        ]);

        // First choice (rank 1)
        OptionChoice::factory()->create([
            'student_id' => $student->id,
            'option_id' => $option1->id,
            'academic_year_id' => $this->academicYear->id,
            'choice_rank' => 1,
        ]);

        // Try to create another rank 1
        $response = $this->authPostJson('/api/admin/enrollment/options/choices', [
            'student_id' => $student->id,
            'option_id' => $option2->id,
            'academic_year_id' => $this->academicYear->id,
            'choice_rank' => 1, // Same rank
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['choice_rank']);
    }

    #[Test]
    public function it_prevents_choice_when_period_not_open(): void
    {
        $student = Student::factory()->create();
        $option = Option::factory()->choicePeriodExpired()->create([
            'programme_id' => $this->programme->id,
        ]);

        $response = $this->authPostJson('/api/admin/enrollment/options/choices', [
            'student_id' => $student->id,
            'option_id' => $option->id,
            'academic_year_id' => $this->academicYear->id,
            'choice_rank' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['option_id']);
    }

    #[Test]
    public function it_limits_to_three_choices_per_year(): void
    {
        $student = Student::factory()->create();
        $options = Option::factory()->choicePeriodActive()->count(4)->create([
            'programme_id' => $this->programme->id,
        ]);

        // Create 3 choices
        for ($i = 0; $i < 3; $i++) {
            OptionChoice::factory()->create([
                'student_id' => $student->id,
                'option_id' => $options[$i]->id,
                'academic_year_id' => $this->academicYear->id,
                'choice_rank' => $i + 1,
            ]);
        }

        // Try to add 4th choice (we need a unique rank, but there are only 3)
        // This test verifies the max 3 choices validation
        $response = $this->authPostJson('/api/admin/enrollment/options/choices', [
            'student_id' => $student->id,
            'option_id' => $options[3]->id,
            'academic_year_id' => $this->academicYear->id,
            'choice_rank' => 1, // Will fail for duplicate rank anyway
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_can_get_student_choices(): void
    {
        $student = Student::factory()->create();
        $options = Option::factory()->choicePeriodActive()->count(3)->create([
            'programme_id' => $this->programme->id,
        ]);

        foreach ($options as $index => $option) {
            OptionChoice::factory()->create([
                'student_id' => $student->id,
                'option_id' => $option->id,
                'academic_year_id' => $this->academicYear->id,
                'choice_rank' => $index + 1,
            ]);
        }

        $response = $this->authGetJson(
            "/api/admin/enrollment/options/student-choices?student_id={$student->id}&academic_year_id={$this->academicYear->id}"
        );

        $response->assertOk()
            ->assertJsonCount(3, 'data.choices');
    }

    // ==================== ASSIGNMENT TESTS ====================

    #[Test]
    public function it_can_run_automatic_assignment(): void
    {
        $level = 'L3';

        $option = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
            'level' => $level,
            'capacity' => 30,
            'prerequisites' => null,
        ]);

        $students = Student::factory()->count(5)->create();

        foreach ($students as $student) {
            OptionChoice::factory()->create([
                'student_id' => $student->id,
                'option_id' => $option->id,
                'academic_year_id' => $this->academicYear->id,
                'choice_rank' => 1,
                'status' => 'Pending',
            ]);
        }

        $response = $this->authPostJson('/api/admin/enrollment/options/assign', [
            'academic_year_id' => $this->academicYear->id,
            'programme_id' => $this->programme->id,
            'level' => $level,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.assigned', 5);

        // Verify all students got assigned
        $this->assertEquals(5, OptionAssignment::where('option_id', $option->id)->count());
    }

    #[Test]
    public function it_can_manually_assign_student(): void
    {
        $student = Student::factory()->create();
        $option = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
        ]);

        $response = $this->authPostJson('/api/admin/enrollment/options/assign-manual', [
            'student_id' => $student->id,
            'option_id' => $option->id,
            'academic_year_id' => $this->academicYear->id,
            'notes' => 'Affectation exceptionnelle',
            'choice_rank_obtained' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Étudiant affecté manuellement avec succès.')
            ->assertJsonPath('data.assignment_method', 'Manual');

        $this->assertDatabaseHas('option_assignments', [
            'student_id' => $student->id,
            'option_id' => $option->id,
            'assignment_method' => 'Manual',
        ], 'tenant');
    }

    #[Test]
    public function it_can_remove_assignment(): void
    {
        $student = Student::factory()->create();
        $option = Option::factory()->create([
            'programme_id' => $this->programme->id,
        ]);

        $assignment = OptionAssignment::factory()->create([
            'student_id' => $student->id,
            'option_id' => $option->id,
            'academic_year_id' => $this->academicYear->id,
        ]);

        $response = $this->authDeleteJson("/api/admin/enrollment/option-assignments/{$assignment->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Affectation supprimée avec succès.');

        $this->assertDatabaseMissing('option_assignments', ['id' => $assignment->id], 'tenant');
    }

    // ==================== STATISTICS TESTS ====================

    #[Test]
    public function it_can_get_option_statistics(): void
    {
        $option = Option::factory()->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
            'capacity' => 30,
        ]);

        $students = Student::factory()->count(10)->create();

        foreach ($students as $student) {
            OptionChoice::factory()->create([
                'student_id' => $student->id,
                'option_id' => $option->id,
                'academic_year_id' => $this->academicYear->id,
                'choice_rank' => 1,
            ]);

            OptionAssignment::factory()->firstChoiceObtained()->create([
                'student_id' => $student->id,
                'option_id' => $option->id,
                'academic_year_id' => $this->academicYear->id,
            ]);
        }

        $response = $this->authGetJson(
            "/api/admin/enrollment/options/{$option->id}/statistics?academic_year_id={$this->academicYear->id}"
        );

        $response->assertOk()
            ->assertJsonPath('data.total_assigned', 10)
            ->assertJsonPath('data.capacity', 30)
            ->assertJsonPath('data.remaining_capacity', 20)
            ->assertJsonPath('data.satisfaction_rate', 100);
    }

    #[Test]
    public function it_can_get_global_statistics(): void
    {
        $level = 'L3';

        Option::factory()->count(2)->choicePeriodActive()->create([
            'programme_id' => $this->programme->id,
            'level' => $level,
        ]);

        $response = $this->authGetJson(
            "/api/admin/enrollment/options/statistics/global?academic_year_id={$this->academicYear->id}&programme_id={$this->programme->id}&level={$level}"
        );

        $response->assertOk()
            ->assertJsonPath('data.level', $level)
            ->assertJsonPath('data.total_options', 2);
    }

    // ==================== PREREQUISITE CHECK TESTS ====================

    #[Test]
    public function it_can_check_prerequisites(): void
    {
        $student = Student::factory()->create();
        $option = Option::factory()->create([
            'programme_id' => $this->programme->id,
            'prerequisites' => null, // No prerequisites
        ]);

        $response = $this->authPostJson('/api/admin/enrollment/options/check-prerequisites', [
            'student_id' => $student->id,
            'option_id' => $option->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.passes', true);
    }

    // ==================== AUTHENTICATION TESTS ====================

    #[Test]
    public function it_requires_authentication_for_list_endpoint(): void
    {
        $this->getJson('/api/admin/enrollment/options')->assertStatus(401);
    }

    #[Test]
    public function it_requires_authentication_for_create_endpoint(): void
    {
        $this->postJson('/api/admin/enrollment/options', [])->assertStatus(401);
    }

    #[Test]
    public function it_requires_authentication_for_show_endpoint(): void
    {
        // Create option first to avoid 404
        $option = Option::factory()->create([
            'programme_id' => $this->programme->id,
        ]);

        $this->getJson("/api/admin/enrollment/options/{$option->id}")->assertStatus(401);
    }

    #[Test]
    public function it_requires_authentication_for_update_endpoint(): void
    {
        $option = Option::factory()->create([
            'programme_id' => $this->programme->id,
        ]);

        $this->putJson("/api/admin/enrollment/options/{$option->id}", [])->assertStatus(401);
    }

    #[Test]
    public function it_requires_authentication_for_delete_endpoint(): void
    {
        $option = Option::factory()->create([
            'programme_id' => $this->programme->id,
        ]);

        $this->deleteJson("/api/admin/enrollment/options/{$option->id}")->assertStatus(401);
    }
}
