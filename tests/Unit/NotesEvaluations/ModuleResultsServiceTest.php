<?php

namespace Tests\Unit\NotesEvaluations;

use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Services\ModuleResultsService;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;
use PHPUnit\Framework\Attributes\Test;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ModuleResultsServiceTest extends TestCase
{
    use InteractsWithTenancy;

    private ModuleResultsService $service;

    private User $user;

    private Module $module;

    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->withoutMiddleware(InitializeTenancyByDomain::class);

        $this->service = new ModuleResultsService;
        $this->user = User::factory()->create();
        $this->module = Module::factory()->create();
        $this->semester = Semester::factory()->create();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    #[Test]
    public function it_calculates_median_correctly_for_odd_count(): void
    {
        $values = collect([5.0, 10.0, 12.0, 14.0, 18.0]);

        $median = $this->service->calculateMedian($values);

        $this->assertEquals(12.0, $median);
    }

    #[Test]
    public function it_calculates_median_correctly_for_even_count(): void
    {
        $values = collect([5.0, 10.0, 12.0, 14.0]);

        $median = $this->service->calculateMedian($values);

        $this->assertEquals(11.0, $median);
    }

    #[Test]
    public function it_returns_null_median_for_empty_collection(): void
    {
        $values = collect([]);

        $median = $this->service->calculateMedian($values);

        $this->assertNull($median);
    }

    #[Test]
    public function it_calculates_standard_deviation_correctly(): void
    {
        // Values: 10, 12, 14 => mean = 12, variance = ((10-12)^2 + (12-12)^2 + (14-12)^2)/3 = (4+0+4)/3 = 2.67
        // stddev = sqrt(2.67) ≈ 1.63
        $values = collect([10.0, 12.0, 14.0]);

        $stdDev = $this->service->calculateStdDev($values);

        $this->assertEqualsWithDelta(1.63, $stdDev, 0.01);
    }

    #[Test]
    public function it_returns_null_stddev_for_empty_collection(): void
    {
        $values = collect([]);

        $stdDev = $this->service->calculateStdDev($values);

        $this->assertNull($stdDev);
    }

    #[Test]
    public function it_calculates_distribution_correctly(): void
    {
        $values = collect([3.0, 4.5, 7.0, 8.5, 9.0, 10.5, 12.0, 13.5, 16.0, 18.0]);

        $distribution = $this->service->calculateDistribution($values);

        $this->assertEquals(2, $distribution['0-5']);
        $this->assertEquals(3, $distribution['5-10']);
        $this->assertEquals(3, $distribution['10-15']);
        $this->assertEquals(2, $distribution['15-20']);
    }

    #[Test]
    public function it_returns_correct_mention(): void
    {
        $this->assertEquals('Très Bien', $this->service->getMention(17.0));
        $this->assertEquals('Très Bien', $this->service->getMention(16.0));
        $this->assertEquals('Bien', $this->service->getMention(15.0));
        $this->assertEquals('Bien', $this->service->getMention(14.0));
        $this->assertEquals('Assez Bien', $this->service->getMention(13.0));
        $this->assertEquals('Assez Bien', $this->service->getMention(12.0));
        $this->assertEquals('Passable', $this->service->getMention(11.0));
        $this->assertEquals('Passable', $this->service->getMention(10.0));
        $this->assertEquals('Non admis', $this->service->getMention(9.0));
        $this->assertEquals('Non admis', $this->service->getMention(5.0));
    }

    #[Test]
    public function it_generates_module_results_with_statistics(): void
    {
        // Create students with grades
        $students = Student::factory()->count(4)->create();

        ModuleGrade::factory()->create([
            'student_id' => $students[0]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 15.0,
            'is_final' => true,
        ]);

        ModuleGrade::factory()->create([
            'student_id' => $students[1]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 12.0,
            'is_final' => true,
        ]);

        ModuleGrade::factory()->create([
            'student_id' => $students[2]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 8.0,
            'is_final' => true,
        ]);

        ModuleGrade::factory()->create([
            'student_id' => $students[3]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 18.0,
            'is_final' => true,
        ]);

        $result = $this->service->generate($this->module->id, $this->semester->id);

        $this->assertEquals(4, $result->total_students);
        $this->assertEquals(13.25, $result->class_average);
        $this->assertEquals(8.0, $result->min_grade);
        $this->assertEquals(18.0, $result->max_grade);
        $this->assertEquals(75.0, $result->pass_rate);
        $this->assertNotNull($result->generated_at);
    }

    #[Test]
    public function it_calculates_rankings_with_ex_aequo(): void
    {
        $students = Student::factory()->count(3)->create();

        $g1 = ModuleGrade::factory()->create([
            'student_id' => $students[0]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 15.0,
            'is_final' => true,
        ]);

        $g2 = ModuleGrade::factory()->create([
            'student_id' => $students[1]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 15.0, // Ex-aequo
            'is_final' => true,
        ]);

        $g3 = ModuleGrade::factory()->create([
            'student_id' => $students[2]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 12.0,
            'is_final' => true,
        ]);

        $this->service->generate($this->module->id, $this->semester->id);

        // Reload grades
        $g1->refresh();
        $g2->refresh();
        $g3->refresh();

        // Both 15.0 should have rank 1
        $this->assertEquals(1, $g1->rank);
        $this->assertEquals(1, $g2->rank);
        // 12.0 should skip rank 2 and be rank 3
        $this->assertEquals(3, $g3->rank);

        // All should have total_ranked = 3
        $this->assertEquals(3, $g1->total_ranked);
        $this->assertEquals(3, $g2->total_ranked);
        $this->assertEquals(3, $g3->total_ranked);
    }

    #[Test]
    public function it_handles_absent_students_in_rankings(): void
    {
        $students = Student::factory()->count(3)->create();

        $g1 = ModuleGrade::factory()->create([
            'student_id' => $students[0]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 15.0,
            'is_final' => true,
        ]);

        $g2 = ModuleGrade::factory()->create([
            'student_id' => $students[1]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => null, // Absent
            'is_final' => true,
            'status' => 'ABS',
        ]);

        $g3 = ModuleGrade::factory()->create([
            'student_id' => $students[2]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 12.0,
            'is_final' => true,
        ]);

        $this->service->generate($this->module->id, $this->semester->id);

        // Reload grades
        $g1->refresh();
        $g2->refresh();
        $g3->refresh();

        $this->assertEquals(1, $g1->rank);
        $this->assertEquals(2, $g1->total_ranked); // Only 2 ranked (excluding absent)
        $this->assertNull($g2->rank); // Absent has no rank
        $this->assertNull($g2->total_ranked);
        $this->assertEquals(2, $g3->rank);
        $this->assertEquals(2, $g3->total_ranked);
    }

    #[Test]
    public function it_gets_students_by_status_correctly(): void
    {
        $students = Student::factory()->count(4)->create();

        // Validated: average >= 10
        ModuleGrade::factory()->create([
            'student_id' => $students[0]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 15.0,
            'is_final' => true,
        ]);

        ModuleGrade::factory()->create([
            'student_id' => $students[1]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 10.0,
            'is_final' => true,
        ]);

        // Failed: average < 10
        ModuleGrade::factory()->create([
            'student_id' => $students[2]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 8.0,
            'is_final' => true,
        ]);

        // Absent: null average
        ModuleGrade::factory()->create([
            'student_id' => $students[3]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => null,
            'is_final' => true,
            'status' => 'ABS',
        ]);

        $result = $this->service->getStudentsByStatus($this->module->id, $this->semester->id);

        $this->assertCount(2, $result['validated']);
        $this->assertCount(1, $result['failed']);
        $this->assertCount(1, $result['absent']);
    }

    #[Test]
    public function it_calculates_pass_rate_correctly(): void
    {
        $students = Student::factory()->count(4)->create();

        // 3 validated, 1 failed
        ModuleGrade::factory()->create([
            'student_id' => $students[0]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 15.0,
            'is_final' => true,
        ]);

        ModuleGrade::factory()->create([
            'student_id' => $students[1]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 12.0,
            'is_final' => true,
        ]);

        ModuleGrade::factory()->create([
            'student_id' => $students[2]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 10.0,
            'is_final' => true,
        ]);

        ModuleGrade::factory()->create([
            'student_id' => $students[3]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 8.0,
            'is_final' => true,
        ]);

        $result = $this->service->generate($this->module->id, $this->semester->id);

        // 3 passed out of 4 = 75%
        $this->assertEquals(75.0, $result->pass_rate);
    }

    #[Test]
    public function it_calculates_absence_rate_correctly(): void
    {
        $students = Student::factory()->count(4)->create();

        // 3 with grades, 1 absent
        ModuleGrade::factory()->create([
            'student_id' => $students[0]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 15.0,
            'is_final' => true,
        ]);

        ModuleGrade::factory()->create([
            'student_id' => $students[1]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 12.0,
            'is_final' => true,
        ]);

        ModuleGrade::factory()->create([
            'student_id' => $students[2]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 10.0,
            'is_final' => true,
        ]);

        ModuleGrade::factory()->create([
            'student_id' => $students[3]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => null,
            'is_final' => true,
            'status' => 'ABS',
        ]);

        $result = $this->service->generate($this->module->id, $this->semester->id);

        // 1 absent out of 4 = 25%
        $this->assertEquals(25.0, $result->absence_rate);
    }

    #[Test]
    public function it_handles_empty_module_gracefully(): void
    {
        $result = $this->service->generate($this->module->id, $this->semester->id);

        $this->assertEquals(0, $result->total_students);
        $this->assertNull($result->class_average);
        $this->assertNull($result->min_grade);
        $this->assertNull($result->max_grade);
        $this->assertNull($result->median);
        $this->assertNull($result->standard_deviation);
        $this->assertNull($result->pass_rate);
        $this->assertNull($result->absence_rate);
    }

    #[Test]
    public function it_publishes_module_results(): void
    {
        $students = Student::factory()->count(2)->create();

        ModuleGrade::factory()->create([
            'student_id' => $students[0]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 15.0,
            'is_final' => true,
        ]);

        ModuleGrade::factory()->create([
            'student_id' => $students[1]->id,
            'module_id' => $this->module->id,
            'semester_id' => $this->semester->id,
            'average' => 12.0,
            'is_final' => true,
        ]);

        // Generate first
        $result = $this->service->generate($this->module->id, $this->semester->id);
        $this->assertNull($result->published_at);

        // Then publish
        $publishedResult = $this->service->publish($this->module->id, $this->semester->id);

        $this->assertNotNull($publishedResult->published_at);
        $this->assertTrue($publishedResult->isPublished());
    }
}
