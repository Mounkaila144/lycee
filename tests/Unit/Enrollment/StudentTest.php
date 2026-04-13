<?php

namespace Tests\Unit\Enrollment;

use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentDocument;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    #[Test]
    public function it_calculates_full_name(): void
    {
        $student = Student::factory()->make([
            'firstname' => 'John',
            'lastname' => 'Doe',
        ]);

        $this->assertEquals('John Doe', $student->full_name);
    }

    #[Test]
    public function it_calculates_age_correctly(): void
    {
        $student = Student::factory()->make([
            'birthdate' => now()->subYears(20)->format('Y-m-d'),
        ]);

        $this->assertEquals(20, $student->age);
    }

    #[Test]
    public function it_detects_active_status(): void
    {
        $student = Student::factory()->make(['status' => 'Actif']);
        $this->assertTrue($student->isActive());

        $student->status = 'Suspendu';
        $this->assertFalse($student->isActive());
    }

    #[Test]
    public function it_detects_suspended_status(): void
    {
        $student = Student::factory()->make(['status' => 'Suspendu']);
        $this->assertTrue($student->isSuspended());
    }

    #[Test]
    public function it_detects_excluded_status(): void
    {
        $student = Student::factory()->make(['status' => 'Exclu']);
        $this->assertTrue($student->isExcluded());
    }

    #[Test]
    public function it_detects_graduated_status(): void
    {
        $student = Student::factory()->make(['status' => 'Diplômé']);
        $this->assertTrue($student->isGraduated());
    }

    #[Test]
    public function it_detects_complete_documents(): void
    {
        $student = Student::factory()->create();

        // No documents
        $this->assertFalse($student->hasCompleteDocuments());

        // Add all required documents
        StudentDocument::factory()->create([
            'student_id' => $student->id,
            'type' => 'certificat_naissance',
        ]);
        StudentDocument::factory()->create([
            'student_id' => $student->id,
            'type' => 'releve_baccalaureat',
        ]);
        StudentDocument::factory()->create([
            'student_id' => $student->id,
            'type' => 'photo_identite',
        ]);
        StudentDocument::factory()->create([
            'student_id' => $student->id,
            'type' => 'cni_passeport',
        ]);

        $student->refresh();
        $this->assertTrue($student->hasCompleteDocuments());
    }

    #[Test]
    public function it_identifies_missing_documents(): void
    {
        $student = Student::factory()->create();

        // Add only 2 documents
        StudentDocument::factory()->create([
            'student_id' => $student->id,
            'type' => 'certificat_naissance',
        ]);
        StudentDocument::factory()->create([
            'student_id' => $student->id,
            'type' => 'photo_identite',
        ]);

        $student->refresh();
        $missing = $student->getMissingDocuments();

        $this->assertCount(2, $missing);
        $this->assertArrayHasKey('releve_baccalaureat', $missing);
        $this->assertArrayHasKey('cni_passeport', $missing);
    }

    #[Test]
    public function it_calculates_completeness_percentage(): void
    {
        $student = Student::factory()->create();

        // No documents = 0%
        $this->assertEquals(0, $student->getCompletenessPercentage());

        // Add 2 out of 4 required documents = 50%
        StudentDocument::factory()->create([
            'student_id' => $student->id,
            'type' => 'certificat_naissance',
        ]);
        StudentDocument::factory()->create([
            'student_id' => $student->id,
            'type' => 'photo_identite',
        ]);

        $student->refresh();
        $this->assertEquals(50, $student->getCompletenessPercentage());

        // Add remaining documents = 100%
        StudentDocument::factory()->create([
            'student_id' => $student->id,
            'type' => 'releve_baccalaureat',
        ]);
        StudentDocument::factory()->create([
            'student_id' => $student->id,
            'type' => 'cni_passeport',
        ]);

        $student->refresh();
        $this->assertEquals(100, $student->getCompletenessPercentage());
    }

    #[Test]
    public function it_finds_potential_duplicates(): void
    {
        Student::factory()->create([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'birthdate' => '2000-01-01',
        ]);
        Student::factory()->create([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'birthdate' => '2000-01-01',
        ]);

        $duplicates = Student::findPotentialDuplicates('John', 'Doe', '2000-01-01');

        $this->assertCount(2, $duplicates);
    }

    #[Test]
    public function it_searches_by_multiple_fields(): void
    {
        Student::factory()->create([
            'matricule' => '2025-INF-001',
            'firstname' => 'John',
            'email' => 'john@example.com',
        ]);
        Student::factory()->create([
            'matricule' => '2025-MATH-002',
            'firstname' => 'Jane',
        ]);

        $query = Student::on('tenant')->search('john');
        $this->assertEquals(1, $query->count());

        $query = Student::on('tenant')->search('2025-INF');
        $this->assertEquals(1, $query->count());
    }
}
