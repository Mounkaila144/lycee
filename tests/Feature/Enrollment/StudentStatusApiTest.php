<?php

namespace Tests\Feature\Enrollment;

use Illuminate\Support\Facades\Event;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentStatusHistory;
use Modules\Enrollment\Events\StudentStatusChanged;
use Modules\UsersGuard\Entities\User;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class StudentStatusApiTest extends TestCase
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

    // ==================== CHANGE STATUS TESTS ====================

    /** @test */
    public function it_can_change_student_status_from_actif_to_suspendu(): void
    {
        Event::fake([StudentStatusChanged::class]);

        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        $response = $this->authPostJson("/api/admin/enrollment/students/{$student->id}/status", [
            'status' => 'Suspendu',
            'reason' => 'Non-paiement des frais de scolarité depuis 3 mois',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'data' => [
                'student' => ['id', 'matricule', 'status'],
                'history' => ['id', 'old_status', 'new_status', 'reason'],
            ],
        ]);

        $this->assertEquals('Suspendu', $response->json('data.student.status'));
        $this->assertEquals('Actif', $response->json('data.history.old_status'));
        $this->assertEquals('Suspendu', $response->json('data.history.new_status'));

        Event::assertDispatched(StudentStatusChanged::class, function ($event) use ($student) {
            return $event->student->id === $student->id
                && $event->oldStatus === 'Actif'
                && $event->newStatus === 'Suspendu';
        });
    }

    /** @test */
    public function it_can_change_student_status_from_actif_to_diplome(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        $response = $this->authPostJson("/api/admin/enrollment/students/{$student->id}/status", [
            'status' => 'Diplômé',
            'reason' => 'L\'étudiant a validé tous les crédits requis pour son diplôme',
            'effective_date' => now()->toDateString(),
        ]);

        $response->assertOk();
        $this->assertEquals('Diplômé', $response->json('data.student.status'));

        // Verify history was created
        $this->assertDatabaseHas('student_status_histories', [
            'student_id' => $student->id,
            'old_status' => 'Actif',
            'new_status' => 'Diplômé',
        ], 'tenant');
    }

    /** @test */
    public function it_can_reactivate_suspended_student(): void
    {
        $student = Student::factory()->create([
            'status' => 'Suspendu',
        ]);

        $response = $this->authPostJson("/api/admin/enrollment/students/{$student->id}/status", [
            'status' => 'Actif',
            'reason' => 'L\'étudiant a régularisé sa situation financière',
        ]);

        $response->assertOk();
        $this->assertEquals('Actif', $response->json('data.student.status'));
    }

    /** @test */
    public function it_rejects_invalid_status_transition(): void
    {
        // A student already "Diplômé" cannot transition to any other status
        $student = Student::factory()->create([
            'status' => 'Diplômé',
        ]);

        $response = $this->authPostJson("/api/admin/enrollment/students/{$student->id}/status", [
            'status' => 'Actif',
            'reason' => 'Tentative de réactivation impossible',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'invalid_status_transition');
    }

    /** @test */
    public function it_rejects_transition_to_same_status(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        $response = $this->authPostJson("/api/admin/enrollment/students/{$student->id}/status", [
            'status' => 'Actif',
            'reason' => 'Tentative de changement vers le même statut',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'invalid_status_transition');
    }

    /** @test */
    public function it_validates_status_change_request(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        // Missing required fields
        $response = $this->authPostJson("/api/admin/enrollment/students/{$student->id}/status", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status', 'reason']);
    }

    /** @test */
    public function it_validates_reason_minimum_length(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        $response = $this->authPostJson("/api/admin/enrollment/students/{$student->id}/status", [
            'status' => 'Suspendu',
            'reason' => 'Court', // Less than 10 characters
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);
    }

    /** @test */
    public function it_validates_invalid_status_value(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        $response = $this->authPostJson("/api/admin/enrollment/students/{$student->id}/status", [
            'status' => 'InvalidStatus',
            'reason' => 'Une raison valide avec plus de dix caractères',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_student(): void
    {
        $response = $this->authPostJson('/api/admin/enrollment/students/99999/status', [
            'status' => 'Suspendu',
            'reason' => 'Une raison valide avec plus de dix caractères',
        ]);

        $response->assertStatus(404);
    }

    // ==================== STATUS HISTORY TESTS ====================

    /** @test */
    public function it_can_get_student_status_history(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        // Create some history records
        StudentStatusHistory::factory()->count(5)->create([
            'student_id' => $student->id,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/students/{$student->id}/status/history");

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $this->assertEquals(5, $response->json('meta.total'));
    }

    /** @test */
    public function it_paginates_status_history(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        StudentStatusHistory::factory()->count(25)->create([
            'student_id' => $student->id,
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/students/{$student->id}/status/history?per_page=10");

        $response->assertOk();
        $this->assertEquals(10, $response->json('meta.per_page'));
        $this->assertEquals(25, $response->json('meta.total'));
        $this->assertEquals(3, $response->json('meta.last_page'));
    }

    /** @test */
    public function it_returns_empty_history_for_new_student(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/students/{$student->id}/status/history");

        $response->assertOk();
        $this->assertEquals(0, $response->json('meta.total'));
    }

    // ==================== AVAILABLE TRANSITIONS TESTS ====================

    /** @test */
    public function it_returns_available_transitions_for_active_student(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/students/{$student->id}/status/transitions");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'current_status',
                'is_final_status',
                'allowed_transitions',
            ],
        ]);

        $this->assertEquals('Actif', $response->json('data.current_status'));
        $this->assertFalse($response->json('data.is_final_status'));

        $allowedTransitions = $response->json('data.allowed_transitions');
        $this->assertContains('Suspendu', $allowedTransitions);
        $this->assertContains('Exclu', $allowedTransitions);
        $this->assertContains('Diplômé', $allowedTransitions);
        $this->assertContains('Abandon', $allowedTransitions);
        $this->assertContains('Transféré', $allowedTransitions);
    }

    /** @test */
    public function it_returns_empty_transitions_for_graduated_student(): void
    {
        $student = Student::factory()->create([
            'status' => 'Diplômé',
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/students/{$student->id}/status/transitions");

        $response->assertOk();
        $this->assertEquals('Diplômé', $response->json('data.current_status'));
        $this->assertTrue($response->json('data.is_final_status'));
        $this->assertEmpty($response->json('data.allowed_transitions'));
    }

    /** @test */
    public function it_returns_limited_transitions_for_suspended_student(): void
    {
        $student = Student::factory()->create([
            'status' => 'Suspendu',
        ]);

        $response = $this->authGetJson("/api/admin/enrollment/students/{$student->id}/status/transitions");

        $response->assertOk();
        $this->assertEquals('Suspendu', $response->json('data.current_status'));
        $this->assertFalse($response->json('data.is_final_status'));

        $allowedTransitions = $response->json('data.allowed_transitions');
        $this->assertContains('Actif', $allowedTransitions);
        $this->assertContains('Exclu', $allowedTransitions);
        $this->assertContains('Abandon', $allowedTransitions);
        $this->assertNotContains('Diplômé', $allowedTransitions);
        $this->assertNotContains('Transféré', $allowedTransitions);
    }

    // ==================== STATUS STATISTICS TESTS ====================

    /** @test */
    public function it_returns_status_statistics(): void
    {
        // Create students with various statuses
        Student::factory()->count(5)->create([
            'status' => 'Actif',
        ]);
        Student::factory()->count(2)->create([
            'status' => 'Suspendu',
        ]);
        Student::factory()->count(1)->create([
            'status' => 'Diplômé',
        ]);

        $response = $this->authGetJson('/api/admin/enrollment/students/status/statistics');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'status_statistics' => [
                    'by_status',
                    'total',
                    'active_percentage',
                ],
                'transition_statistics',
            ],
        ]);

        $statusStats = $response->json('data.status_statistics');
        $this->assertEquals(8, $statusStats['total']);
        $this->assertEquals(5, $statusStats['by_status']['Actif']);
        $this->assertEquals(2, $statusStats['by_status']['Suspendu']);
        $this->assertEquals(1, $statusStats['by_status']['Diplômé']);
    }

    /** @test */
    public function it_returns_transition_statistics_with_date_range(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        // Create historical transitions
        StudentStatusHistory::factory()->create([
            'student_id' => $student->id,
            'old_status' => 'Actif',
            'new_status' => 'Suspendu',
            'effective_date' => now()->subDays(5)->toDateString(),
        ]);

        StudentStatusHistory::factory()->create([
            'student_id' => $student->id,
            'old_status' => 'Suspendu',
            'new_status' => 'Actif',
            'effective_date' => now()->subDays(2)->toDateString(),
        ]);

        $startDate = now()->subDays(10)->toDateString();
        $endDate = now()->toDateString();

        $response = $this->authGetJson("/api/admin/enrollment/students/status/statistics?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();
        $this->assertNotNull($response->json('data.transition_statistics'));
    }

    // ==================== AUTHENTICATION TESTS ====================

    /** @test */
    public function it_requires_authentication_for_status_change(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        $response = $this->postJson("/api/admin/enrollment/students/{$student->id}/status", [
            'status' => 'Suspendu',
            'reason' => 'Test sans authentification',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_authentication_for_status_history(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        $response = $this->getJson("/api/admin/enrollment/students/{$student->id}/status/history");

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_authentication_for_available_transitions(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        $response = $this->getJson("/api/admin/enrollment/students/{$student->id}/status/transitions");

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_authentication_for_status_statistics(): void
    {
        $response = $this->getJson('/api/admin/enrollment/students/status/statistics');

        $response->assertStatus(401);
    }

    // ==================== EVENT TESTS ====================

    /** @test */
    public function it_dispatches_correct_event_for_exclusion(): void
    {
        Event::fake([StudentStatusChanged::class]);

        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        $this->authPostJson("/api/admin/enrollment/students/{$student->id}/status", [
            'status' => 'Exclu',
            'reason' => 'Fraude académique confirmée par le conseil de discipline',
        ]);

        Event::assertDispatched(StudentStatusChanged::class, function ($event) {
            return $event->isExclusion() === true;
        });
    }

    /** @test */
    public function it_dispatches_correct_event_for_graduation(): void
    {
        Event::fake([StudentStatusChanged::class]);

        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        $this->authPostJson("/api/admin/enrollment/students/{$student->id}/status", [
            'status' => 'Diplômé',
            'reason' => 'Validation complète du parcours académique',
        ]);

        Event::assertDispatched(StudentStatusChanged::class, function ($event) {
            return $event->isGraduation() === true;
        });
    }

    /** @test */
    public function it_dispatches_correct_event_for_reactivation(): void
    {
        Event::fake([StudentStatusChanged::class]);

        $student = Student::factory()->create([
            'status' => 'Suspendu',
        ]);

        $this->authPostJson("/api/admin/enrollment/students/{$student->id}/status", [
            'status' => 'Actif',
            'reason' => 'Situation régularisée après appel',
        ]);

        Event::assertDispatched(StudentStatusChanged::class, function ($event) {
            return $event->isReactivation() === true;
        });
    }
}
