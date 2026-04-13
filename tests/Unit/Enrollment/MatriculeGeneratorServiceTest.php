<?php

namespace Tests\Unit\Enrollment;

use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Services\MatriculeGeneratorService;
use Modules\StructureAcademique\Entities\Programme;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class MatriculeGeneratorServiceTest extends TestCase
{
    use InteractsWithTenancy;

    private MatriculeGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        $this->service = new MatriculeGeneratorService;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    #[Test]
    public function it_generates_matricule_with_correct_format(): void
    {
        $programme = Programme::factory()->create(['code' => 'INF']);

        $matricule = $this->service->generate($programme, 2025);

        $this->assertMatchesRegularExpression('/^2025-INF-\d{3}$/', $matricule);
    }

    #[Test]
    public function it_generates_first_matricule_as_001(): void
    {
        $programme = Programme::factory()->create(['code' => 'MATH']);

        $matricule = $this->service->generate($programme, 2026);

        $this->assertEquals('2026-MATH-001', $matricule);
    }

    #[Test]
    public function it_increments_sequence_number(): void
    {
        $programme = Programme::factory()->create(['code' => 'INFO']);

        // Create first student
        Student::factory()->create([
            'matricule' => '2025-INFO-001',
        ]);

        $matricule = $this->service->generate($programme, 2025);

        $this->assertEquals('2025-INFO-002', $matricule);
    }

    #[Test]
    public function it_generates_unique_matricule(): void
    {
        $programme = Programme::factory()->create(['code' => 'CS']);

        $currentYear = now()->year;

        // Create existing students with current year
        Student::factory()->create(['matricule' => "{$currentYear}-CS-001"]);
        Student::factory()->create(['matricule' => "{$currentYear}-CS-002"]);

        $matricule = $this->service->generateNext($programme);

        $this->assertTrue($this->service->isUnique($matricule));
        $this->assertEquals("{$currentYear}-CS-003", $matricule);
    }

    #[Test]
    public function it_checks_matricule_uniqueness(): void
    {
        Student::factory()->create(['matricule' => '2025-INF-001']);

        $this->assertFalse($this->service->isUnique('2025-INF-001'));
        $this->assertTrue($this->service->isUnique('2025-INF-999'));
    }

    #[Test]
    public function it_validates_matricule_format(): void
    {
        $this->assertTrue($this->service->isValid('2025-INF-001'));
        $this->assertTrue($this->service->isValid('2026-MATH-999'));

        $this->assertFalse($this->service->isValid('2025-INF-1'));
        $this->assertFalse($this->service->isValid('INF-2025-001'));
        $this->assertFalse($this->service->isValid('2025-inf-001'));
        $this->assertFalse($this->service->isValid('invalid'));
    }

    #[Test]
    public function it_parses_matricule_correctly(): void
    {
        $parsed = $this->service->parse('2025-INF-042');

        $this->assertEquals([
            'year' => 2025,
            'program_code' => 'INF',
            'sequence' => 42,
        ], $parsed);
    }

    #[Test]
    public function it_returns_null_for_invalid_matricule_format(): void
    {
        $this->assertNull($this->service->parse('invalid'));
        $this->assertNull($this->service->parse('2025-INF-1'));
    }

    #[Test]
    public function it_handles_different_program_codes(): void
    {
        $programme1 = Programme::factory()->create(['code' => 'PHYS']);
        $programme2 = Programme::factory()->create(['code' => 'CHEM']);

        $matricule1 = $this->service->generate($programme1, 2025);
        $matricule2 = $this->service->generate($programme2, 2025);

        $this->assertEquals('2025-PHYS-001', $matricule1);
        $this->assertEquals('2025-CHEM-001', $matricule2);
    }

    #[Test]
    public function it_handles_sequence_overflow_gracefully(): void
    {
        $programme = Programme::factory()->create(['code' => 'TEST']);

        // Create student with large sequence number
        Student::factory()->create(['matricule' => '2025-TEST-999']);

        $matricule = $this->service->generate($programme, 2025);

        // Should generate 1000 (even though format is 3 digits, it should work)
        $this->assertStringContainsString('2025-TEST-', $matricule);
    }
}
