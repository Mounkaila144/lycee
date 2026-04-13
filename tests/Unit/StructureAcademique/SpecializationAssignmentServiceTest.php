<?php

namespace Tests\Unit\StructureAcademique;

use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Specialization;
use Modules\StructureAcademique\Entities\StudentSpecialization;
use Modules\StructureAcademique\Services\SpecializationAssignmentService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class SpecializationAssignmentServiceTest extends TestCase
{
    use InteractsWithTenancy;

    private SpecializationAssignmentService $service;

    private Programme $programme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        $this->service = new SpecializationAssignmentService;
        $this->programme = $this->createProgramme();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function createProgramme(array $attributes = []): Programme
    {
        return Programme::create(array_merge([
            'code' => 'TEST-'.uniqid(),
            'libelle' => 'Programme Test',
            'type' => 'Licence',
            'duree_annees' => 3,
            'statut' => 'Actif',
        ], $attributes));
    }

    #[Test]
    public function it_allows_application_when_all_conditions_met(): void
    {
        $specialization = Specialization::factory()
            ->forProgramme($this->programme)
            ->create([
                'min_average_required' => 10.00,
                'application_start_date' => now()->subDay(),
                'application_end_date' => now()->addMonth(),
                'is_active' => true,
            ]);

        $result = $this->service->apply(
            studentId: 1,
            specializationId: $specialization->id,
            studentAverage: 14.5,
            preferenceOrder: 1
        );

        $this->assertTrue($result->success);
        $this->assertEquals('Candidature enregistrée avec succès.', $result->message);
        $this->assertNotNull($result->data['application']);
    }

    #[Test]
    public function it_rejects_application_when_specialization_inactive(): void
    {
        $specialization = Specialization::factory()
            ->forProgramme($this->programme)
            ->inactive()
            ->create();

        $result = $this->service->apply(
            studentId: 1,
            specializationId: $specialization->id,
            studentAverage: 14.5
        );

        $this->assertFalse($result->success);
        $this->assertEquals('Cette spécialité n\'est pas active.', $result->message);
    }

    #[Test]
    public function it_rejects_application_when_period_closed(): void
    {
        $specialization = Specialization::factory()
            ->forProgramme($this->programme)
            ->applicationClosed()
            ->create();

        $result = $this->service->apply(
            studentId: 1,
            specializationId: $specialization->id,
            studentAverage: 14.5
        );

        $this->assertFalse($result->success);
        $this->assertEquals('La période de candidature n\'est pas ouverte.', $result->message);
    }

    #[Test]
    public function it_rejects_application_with_insufficient_average(): void
    {
        $specialization = Specialization::factory()
            ->forProgramme($this->programme)
            ->create([
                'min_average_required' => 14.00,
                'application_start_date' => now()->subDay(),
                'application_end_date' => now()->addMonth(),
            ]);

        $result = $this->service->apply(
            studentId: 1,
            specializationId: $specialization->id,
            studentAverage: 12.0
        );

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Moyenne insuffisante', $result->message);
    }

    #[Test]
    public function it_prevents_duplicate_application(): void
    {
        $specialization = Specialization::factory()
            ->forProgramme($this->programme)
            ->create([
                'application_start_date' => now()->subDay(),
                'application_end_date' => now()->addMonth(),
            ]);

        // First application
        $this->service->apply(1, $specialization->id, 14.5);

        // Second application
        $result = $this->service->apply(1, $specialization->id, 14.5);

        $this->assertFalse($result->success);
        $this->assertEquals('Vous avez déjà candidaté à cette spécialité.', $result->message);
    }

    #[Test]
    public function it_assigns_students_by_average_criteria(): void
    {
        $specialization = Specialization::factory()
            ->forProgramme($this->programme)
            ->withCapacity(2)
            ->create();

        // Create 3 applications with different averages
        StudentSpecialization::create([
            'student_id' => 1,
            'specialization_id' => $specialization->id,
            'application_date' => now(),
            'status' => 'En attente',
            'average_at_application' => 12.0,
        ]);

        StudentSpecialization::create([
            'student_id' => 2,
            'specialization_id' => $specialization->id,
            'application_date' => now(),
            'status' => 'En attente',
            'average_at_application' => 16.0,
        ]);

        StudentSpecialization::create([
            'student_id' => 3,
            'specialization_id' => $specialization->id,
            'application_date' => now(),
            'status' => 'En attente',
            'average_at_application' => 14.0,
        ]);

        $result = $this->service->assignStudents($specialization, 'average');

        $this->assertTrue($result->success);
        $this->assertEquals(2, $result->data['accepted']);
        $this->assertEquals(1, $result->data['waitlisted']);

        // Verify highest averages are accepted
        $this->assertDatabaseHas('student_specializations', [
            'student_id' => 2,
            'status' => 'Accepté',
        ], 'tenant');

        $this->assertDatabaseHas('student_specializations', [
            'student_id' => 3,
            'status' => 'Accepté',
        ], 'tenant');

        $this->assertDatabaseHas('student_specializations', [
            'student_id' => 1,
            'status' => 'Liste attente',
        ], 'tenant');
    }

    #[Test]
    public function it_assigns_students_by_application_date_criteria(): void
    {
        $specialization = Specialization::factory()
            ->forProgramme($this->programme)
            ->withCapacity(1)
            ->create();

        StudentSpecialization::create([
            'student_id' => 1,
            'specialization_id' => $specialization->id,
            'application_date' => now()->subDays(2),
            'status' => 'En attente',
            'average_at_application' => 12.0,
        ]);

        StudentSpecialization::create([
            'student_id' => 2,
            'specialization_id' => $specialization->id,
            'application_date' => now()->subDay(),
            'status' => 'En attente',
            'average_at_application' => 16.0,
        ]);

        $result = $this->service->assignStudents($specialization, 'application_date');

        $this->assertTrue($result->success);

        // First applicant should be accepted (earlier date)
        $this->assertDatabaseHas('student_specializations', [
            'student_id' => 1,
            'status' => 'Accepté',
        ], 'tenant');

        $this->assertDatabaseHas('student_specializations', [
            'student_id' => 2,
            'status' => 'Liste attente',
        ], 'tenant');
    }

    #[Test]
    public function it_returns_failure_when_no_pending_applications(): void
    {
        $specialization = Specialization::factory()
            ->forProgramme($this->programme)
            ->create();

        $result = $this->service->assignStudents($specialization);

        $this->assertFalse($result->success);
        $this->assertEquals('Aucune candidature en attente.', $result->message);
    }

    #[Test]
    public function it_can_get_candidates_filtered_by_status(): void
    {
        $specialization = Specialization::factory()
            ->forProgramme($this->programme)
            ->create();

        StudentSpecialization::create([
            'student_id' => 1,
            'specialization_id' => $specialization->id,
            'application_date' => now(),
            'status' => 'En attente',
            'average_at_application' => 14.0,
        ]);

        StudentSpecialization::create([
            'student_id' => 2,
            'specialization_id' => $specialization->id,
            'application_date' => now(),
            'status' => 'Accepté',
            'average_at_application' => 16.0,
            'assigned_at' => now(),
        ]);

        $pendingCandidates = $this->service->getCandidates($specialization, 'En attente');
        $acceptedCandidates = $this->service->getCandidates($specialization, 'Accepté');
        $allCandidates = $this->service->getCandidates($specialization);

        $this->assertCount(1, $pendingCandidates);
        $this->assertCount(1, $acceptedCandidates);
        $this->assertCount(2, $allCandidates);
    }

    #[Test]
    public function it_can_cancel_pending_application(): void
    {
        $specialization = Specialization::factory()
            ->forProgramme($this->programme)
            ->create();

        StudentSpecialization::create([
            'student_id' => 1,
            'specialization_id' => $specialization->id,
            'application_date' => now(),
            'status' => 'En attente',
            'average_at_application' => 14.0,
        ]);

        $result = $this->service->cancelApplication(1, $specialization->id);

        $this->assertTrue($result->success);
        $this->assertEquals('Candidature annulée avec succès.', $result->message);

        $this->assertDatabaseMissing('student_specializations', [
            'student_id' => 1,
            'specialization_id' => $specialization->id,
        ], 'tenant');
    }

    #[Test]
    public function it_cannot_cancel_accepted_application(): void
    {
        $specialization = Specialization::factory()
            ->forProgramme($this->programme)
            ->create();

        StudentSpecialization::create([
            'student_id' => 1,
            'specialization_id' => $specialization->id,
            'application_date' => now(),
            'status' => 'Accepté',
            'average_at_application' => 14.0,
            'assigned_at' => now(),
        ]);

        $result = $this->service->cancelApplication(1, $specialization->id);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Impossible d\'annuler', $result->message);
    }

    #[Test]
    public function it_can_promote_from_waitlist(): void
    {
        $specialization = Specialization::factory()
            ->forProgramme($this->programme)
            ->withCapacity(2)
            ->create();

        StudentSpecialization::create([
            'student_id' => 1,
            'specialization_id' => $specialization->id,
            'application_date' => now(),
            'status' => 'Accepté',
            'average_at_application' => 16.0,
            'assigned_at' => now(),
        ]);

        StudentSpecialization::create([
            'student_id' => 2,
            'specialization_id' => $specialization->id,
            'application_date' => now(),
            'status' => 'Liste attente',
            'average_at_application' => 14.0,
        ]);

        $result = $this->service->promoteFromWaitlist($specialization);

        $this->assertTrue($result->success);
        $this->assertEquals('Étudiant promu depuis la liste d\'attente.', $result->message);

        $this->assertDatabaseHas('student_specializations', [
            'student_id' => 2,
            'status' => 'Accepté',
        ], 'tenant');
    }

    #[Test]
    public function it_cannot_promote_when_specialization_full(): void
    {
        $specialization = Specialization::factory()
            ->forProgramme($this->programme)
            ->withCapacity(1)
            ->create();

        StudentSpecialization::create([
            'student_id' => 1,
            'specialization_id' => $specialization->id,
            'application_date' => now(),
            'status' => 'Accepté',
            'average_at_application' => 16.0,
            'assigned_at' => now(),
        ]);

        StudentSpecialization::create([
            'student_id' => 2,
            'specialization_id' => $specialization->id,
            'application_date' => now(),
            'status' => 'Liste attente',
            'average_at_application' => 14.0,
        ]);

        $result = $this->service->promoteFromWaitlist($specialization);

        $this->assertFalse($result->success);
        $this->assertEquals('La spécialité est pleine.', $result->message);
    }
}
