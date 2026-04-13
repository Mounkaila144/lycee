<?php

namespace Tests\Unit\Enrollment;

use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentStatusHistory;
use Modules\Enrollment\Exceptions\InvalidStatusTransitionException;
use Modules\Enrollment\Services\StudentStatusService;
use Modules\UsersGuard\Entities\User;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class StudentStatusServiceTest extends TestCase
{
    use InteractsWithTenancy;

    private StudentStatusService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->service = new StudentStatusService;
        $this->user = User::factory()->create();

        // Authenticate the user for status changes
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    // ==================== TRANSITION VALIDATION TESTS ====================

    /** @test */
    public function it_allows_valid_transitions_from_actif(): void
    {
        $allowedTransitions = ['Suspendu', 'Exclu', 'Diplômé', 'Abandon', 'Transféré'];

        foreach ($allowedTransitions as $toStatus) {
            $this->assertTrue(
                $this->service->isTransitionAllowed('Actif', $toStatus),
                "Transition from Actif to {$toStatus} should be allowed"
            );
        }
    }

    /** @test */
    public function it_allows_valid_transitions_from_suspendu(): void
    {
        $allowedTransitions = ['Actif', 'Exclu', 'Abandon'];

        foreach ($allowedTransitions as $toStatus) {
            $this->assertTrue(
                $this->service->isTransitionAllowed('Suspendu', $toStatus),
                "Transition from Suspendu to {$toStatus} should be allowed"
            );
        }
    }

    /** @test */
    public function it_rejects_transitions_from_final_statuses(): void
    {
        $finalStatuses = ['Exclu', 'Diplômé', 'Abandon', 'Transféré'];

        foreach ($finalStatuses as $finalStatus) {
            foreach (StudentStatusService::STATUSES as $toStatus) {
                if ($finalStatus !== $toStatus) {
                    $this->assertFalse(
                        $this->service->isTransitionAllowed($finalStatus, $toStatus),
                        "Transition from {$finalStatus} to {$toStatus} should NOT be allowed"
                    );
                }
            }
        }
    }

    /** @test */
    public function it_rejects_same_status_transition(): void
    {
        foreach (StudentStatusService::STATUSES as $status) {
            $this->assertFalse(
                $this->service->isTransitionAllowed($status, $status),
                "Transition from {$status} to {$status} should NOT be allowed"
            );
        }
    }

    /** @test */
    public function it_correctly_identifies_final_statuses(): void
    {
        $finalStatuses = ['Exclu', 'Diplômé', 'Abandon', 'Transféré'];
        $nonFinalStatuses = ['Actif', 'Suspendu'];

        foreach ($finalStatuses as $status) {
            $this->assertTrue(
                $this->service->isFinalStatus($status),
                "{$status} should be a final status"
            );
        }

        foreach ($nonFinalStatuses as $status) {
            $this->assertFalse(
                $this->service->isFinalStatus($status),
                "{$status} should NOT be a final status"
            );
        }
    }

    /** @test */
    public function it_returns_correct_allowed_transitions_for_actif(): void
    {
        $transitions = $this->service->getAllowedTransitions('Actif');

        $this->assertCount(5, $transitions);
        $this->assertContains('Suspendu', $transitions);
        $this->assertContains('Exclu', $transitions);
        $this->assertContains('Diplômé', $transitions);
        $this->assertContains('Abandon', $transitions);
        $this->assertContains('Transféré', $transitions);
    }

    /** @test */
    public function it_returns_correct_allowed_transitions_for_suspendu(): void
    {
        $transitions = $this->service->getAllowedTransitions('Suspendu');

        $this->assertCount(3, $transitions);
        $this->assertContains('Actif', $transitions);
        $this->assertContains('Exclu', $transitions);
        $this->assertContains('Abandon', $transitions);
    }

    /** @test */
    public function it_returns_empty_transitions_for_final_statuses(): void
    {
        $finalStatuses = ['Exclu', 'Diplômé', 'Abandon', 'Transféré'];

        foreach ($finalStatuses as $status) {
            $transitions = $this->service->getAllowedTransitions($status);
            $this->assertEmpty($transitions, "{$status} should have no allowed transitions");
        }
    }

    // ==================== STATUS CHANGE TESTS ====================

    /** @test */
    public function it_changes_student_status_successfully(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        $history = $this->service->changeStatus(
            $student,
            'Suspendu',
            'Non-paiement des frais depuis 3 mois'
        );

        $this->assertInstanceOf(StudentStatusHistory::class, $history);
        $this->assertEquals('Actif', $history->old_status);
        $this->assertEquals('Suspendu', $history->new_status);

        $student->refresh();
        $this->assertEquals('Suspendu', $student->status);
    }

    /** @test */
    public function it_creates_history_record_on_status_change(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        $reason = 'Validation de tous les crédits requis';

        $this->service->changeStatus($student, 'Diplômé', $reason);

        $this->assertDatabaseHas('student_status_histories', [
            'student_id' => $student->id,
            'old_status' => 'Actif',
            'new_status' => 'Diplômé',
            'reason' => $reason,
            'changed_by' => $this->user->id,
        ], 'tenant');
    }

    /** @test */
    public function it_throws_exception_for_invalid_transition(): void
    {
        $student = Student::factory()->create([
            'status' => 'Diplômé',
        ]);

        $this->expectException(InvalidStatusTransitionException::class);

        $this->service->changeStatus($student, 'Actif', 'Tentative de réactivation impossible');
    }

    /** @test */
    public function it_throws_exception_for_same_status(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        $this->expectException(InvalidStatusTransitionException::class);

        $this->service->changeStatus($student, 'Actif', 'Changement vers le même statut');
    }

    /** @test */
    public function it_sets_effective_date_to_today_by_default(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        $history = $this->service->changeStatus($student, 'Suspendu', 'Raison valide de suspension');

        // effective_date is cast to date, so compare as formatted string
        $this->assertEquals(now()->toDateString(), $history->effective_date->toDateString());
    }

    /** @test */
    public function it_uses_provided_effective_date(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        $futureDate = now()->addDays(7)->toDateString();

        $history = $this->service->changeStatus(
            $student,
            'Suspendu',
            'Raison valide de suspension',
            $futureDate
        );

        // effective_date is cast to date, so compare as formatted string
        $this->assertEquals($futureDate, $history->effective_date->toDateString());
    }

    // ==================== STATISTICS TESTS ====================

    /** @test */
    public function it_returns_correct_status_statistics(): void
    {
        // Create students with various statuses
        Student::factory()->count(10)->create([
            'status' => 'Actif',
        ]);
        Student::factory()->count(3)->create([
            'status' => 'Suspendu',
        ]);
        Student::factory()->count(5)->create([
            'status' => 'Diplômé',
        ]);

        $stats = $this->service->getStatusStatistics();

        $this->assertEquals(18, $stats['total']);
        $this->assertEquals(10, $stats['by_status']['Actif']);
        $this->assertEquals(3, $stats['by_status']['Suspendu']);
        $this->assertEquals(5, $stats['by_status']['Diplômé']);
        $this->assertEquals(0, $stats['by_status']['Exclu']);
        $this->assertEquals(0, $stats['by_status']['Abandon']);
        $this->assertEquals(0, $stats['by_status']['Transféré']);
    }

    /** @test */
    public function it_calculates_active_percentage_correctly(): void
    {
        Student::factory()->count(5)->create([
            'status' => 'Actif',
        ]);
        Student::factory()->count(5)->create([
            'status' => 'Diplômé',
        ]);

        $stats = $this->service->getStatusStatistics();

        $this->assertEquals(50.0, $stats['active_percentage']);
    }

    /** @test */
    public function it_handles_zero_students_in_statistics(): void
    {
        // No students created

        $stats = $this->service->getStatusStatistics();

        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['active_percentage']);
    }

    /** @test */
    public function it_returns_transition_statistics(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        // Create some transitions
        StudentStatusHistory::factory()->count(3)->create([
            'student_id' => $student->id,
            'old_status' => 'Actif',
            'new_status' => 'Suspendu',
        ]);

        StudentStatusHistory::factory()->count(2)->create([
            'student_id' => $student->id,
            'old_status' => 'Suspendu',
            'new_status' => 'Actif',
        ]);

        $stats = $this->service->getTransitionStatistics();

        $this->assertIsArray($stats);
        $this->assertNotEmpty($stats);
    }

    // ==================== HISTORY TESTS ====================

    /** @test */
    public function it_retrieves_paginated_status_history(): void
    {
        $student = Student::factory()->create([
            'status' => 'Actif',
        ]);

        StudentStatusHistory::factory()->count(30)->create([
            'student_id' => $student->id,
        ]);

        $history = $this->service->getStatusHistory($student->id, 10);

        $this->assertEquals(10, $history->perPage());
        $this->assertEquals(30, $history->total());
        $this->assertEquals(3, $history->lastPage());
    }
}
