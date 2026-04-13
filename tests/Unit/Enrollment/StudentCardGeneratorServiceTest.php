<?php

namespace Tests\Unit\Enrollment;

use Illuminate\Support\Facades\Queue;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentCard;
use Modules\Enrollment\Jobs\GenerateCardPdfJob;
use Modules\Enrollment\Services\StudentCardGeneratorService;
use Modules\StructureAcademique\Entities\AcademicYear;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class StudentCardGeneratorServiceTest extends TestCase
{
    use InteractsWithTenancy;

    private StudentCardGeneratorService $service;

    private AcademicYear $academicYear;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->service = app(StudentCardGeneratorService::class);
        $this->academicYear = AcademicYear::factory()->active()->create();
        $this->student = Student::factory()->create(['status' => 'Actif']);

        Queue::fake();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    #[Test]
    public function it_generates_student_card(): void
    {
        $card = $this->service->generate($this->student, $this->academicYear);

        $this->assertInstanceOf(StudentCard::class, $card);
        $this->assertEquals($this->student->id, $card->student_id);
        $this->assertEquals($this->academicYear->id, $card->academic_year_id);
        $this->assertEquals(StudentCard::STATUS_ACTIVE, $card->status);
        $this->assertFalse($card->is_duplicate);
        $this->assertNotEmpty($card->card_number);
        $this->assertNotEmpty($card->qr_signature);

        Queue::assertPushed(GenerateCardPdfJob::class);
    }

    #[Test]
    public function it_generates_unique_card_numbers(): void
    {
        $card1 = $this->service->generate($this->student, $this->academicYear);

        $student2 = Student::factory()->create();
        $card2 = $this->service->generate($student2, $this->academicYear);

        $this->assertNotEquals($card1->card_number, $card2->card_number);
    }

    #[Test]
    public function it_returns_existing_card_if_not_duplicate(): void
    {
        $existingCard = $this->service->generate($this->student, $this->academicYear);
        $returnedCard = $this->service->generate($this->student, $this->academicYear);

        $this->assertEquals($existingCard->id, $returnedCard->id);
    }

    #[Test]
    public function it_generates_duplicate_card(): void
    {
        $originalCard = $this->service->generate($this->student, $this->academicYear);
        $duplicateCard = $this->service->generateDuplicate($this->student, $this->academicYear);

        $this->assertNotEquals($originalCard->id, $duplicateCard->id);
        $this->assertTrue($duplicateCard->is_duplicate);
        $this->assertEquals($originalCard->id, $duplicateCard->original_card_id);
    }

    #[Test]
    public function it_batch_generates_cards(): void
    {
        $students = Student::factory()->count(3)->create();

        $results = $this->service->batchGenerate(
            $students->pluck('id')->toArray(),
            $this->academicYear
        );

        $this->assertCount(3, $results['generated']);
        $this->assertEmpty($results['skipped']);
        $this->assertEmpty($results['failed']);
    }

    #[Test]
    public function it_skips_existing_cards_in_batch(): void
    {
        $existingCard = StudentCard::factory()->create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->academicYear->id,
            'is_duplicate' => false,
        ]);

        $newStudent = Student::factory()->create();

        $results = $this->service->batchGenerate(
            [$this->student->id, $newStudent->id],
            $this->academicYear
        );

        $this->assertCount(1, $results['generated']);
        $this->assertCount(1, $results['skipped']);
        $this->assertEquals($this->student->id, $results['skipped'][0]['student_id']);
    }

    #[Test]
    public function it_reports_failures_for_nonexistent_students(): void
    {
        $results = $this->service->batchGenerate(
            [99999],
            $this->academicYear
        );

        $this->assertEmpty($results['generated']);
        $this->assertCount(1, $results['failed']);
    }

    #[Test]
    public function it_updates_card_status(): void
    {
        $card = StudentCard::factory()->active()->create([
            'academic_year_id' => $this->academicYear->id,
        ]);

        $updated = $this->service->updateStatus($card, StudentCard::STATUS_SUSPENDED);

        $this->assertEquals(StudentCard::STATUS_SUSPENDED, $updated->status);
    }

    #[Test]
    public function it_throws_exception_for_invalid_status(): void
    {
        $card = StudentCard::factory()->create([
            'academic_year_id' => $this->academicYear->id,
        ]);

        $this->expectException(\Exception::class);

        $this->service->updateStatus($card, 'InvalidStatus');
    }

    #[Test]
    public function it_updates_print_status_to_printed(): void
    {
        $card = StudentCard::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'print_status' => StudentCard::PRINT_STATUS_PENDING,
        ]);

        $updated = $this->service->updatePrintStatus($card, StudentCard::PRINT_STATUS_PRINTED);

        $this->assertEquals(StudentCard::PRINT_STATUS_PRINTED, $updated->print_status);
        $this->assertNotNull($updated->printed_at);
    }

    #[Test]
    public function it_updates_print_status_to_delivered(): void
    {
        $card = StudentCard::factory()->create([
            'academic_year_id' => $this->academicYear->id,
            'print_status' => StudentCard::PRINT_STATUS_PRINTED,
            'printed_at' => now()->subDay(),
        ]);

        $updated = $this->service->updatePrintStatus($card, StudentCard::PRINT_STATUS_DELIVERED);

        $this->assertEquals(StudentCard::PRINT_STATUS_DELIVERED, $updated->print_status);
        $this->assertNotNull($updated->delivered_at);
    }

    #[Test]
    public function it_verifies_valid_card(): void
    {
        $card = $this->service->generate($this->student, $this->academicYear);

        $qrData = json_decode($card->qr_code_data, true);

        $result = $this->service->verifyCard($card->qr_code_data, $card->qr_signature);

        $this->assertTrue($result['valid']);
        $this->assertEquals($card->id, $result['card']->id);
        $this->assertEquals($this->student->id, $result['student']->id);
    }

    #[Test]
    public function it_throws_exception_for_invalid_signature(): void
    {
        $card = $this->service->generate($this->student, $this->academicYear);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid card signature');

        $this->service->verifyCard($card->qr_code_data, 'invalid_signature');
    }

    #[Test]
    public function it_throws_exception_for_expired_card(): void
    {
        // Generate a valid card first via the service
        $card = $this->service->generate($this->student, $this->academicYear);

        // Manually set the card as expired
        $card->update([
            'status' => StudentCard::STATUS_EXPIRED,
            'valid_until' => now()->subMonth(),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Expired');

        $this->service->verifyCard($card->qr_code_data, $card->qr_signature);
    }

    #[Test]
    public function it_throws_exception_for_suspended_card(): void
    {
        // Generate a valid card first via the service
        $card = $this->service->generate($this->student, $this->academicYear);

        // Update the status to suspended
        $card->update(['status' => StudentCard::STATUS_SUSPENDED]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Suspended');

        $this->service->verifyCard($card->qr_code_data, $card->qr_signature);
    }

    #[Test]
    public function it_calculates_statistics(): void
    {
        StudentCard::factory()->count(3)->active()->create([
            'academic_year_id' => $this->academicYear->id,
        ]);

        StudentCard::factory()->count(2)->create([
            'academic_year_id' => $this->academicYear->id,
            'is_duplicate' => true,
        ]);

        StudentCard::factory()->delivered()->create([
            'academic_year_id' => $this->academicYear->id,
        ]);

        $stats = $this->service->getStatistics($this->academicYear->id);

        $this->assertEquals(6, $stats['total']);
        $this->assertEquals(4, $stats['originals']);
        $this->assertEquals(2, $stats['duplicates']);
    }
}
