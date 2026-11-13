<?php

namespace Tests\Feature\Enrollment;

use Illuminate\Database\QueryException;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Database\Seeders\CycleAndLevelSeeder;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class ClassEnrollmentTest extends TestCase
{
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        (new CycleAndLevelSeeder)->run();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    #[Test]
    public function it_allows_only_one_class_per_student_per_year_but_keeps_yearly_progression_history(): void
    {
        $student = Student::factory()->create();
        $sixthLevel = Level::on('tenant')->where('code', '6E')->firstOrFail();
        $fifthLevel = Level::on('tenant')->where('code', '5E')->firstOrFail();

        $yearOne = AcademicYear::on('tenant')->create([
            'name' => '2026-2027',
            'start_date' => '2026-10-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);
        $yearTwo = AcademicYear::on('tenant')->create([
            'name' => '2027-2028',
            'start_date' => '2027-10-01',
            'end_date' => '2028-06-30',
            'is_active' => false,
        ]);

        $sixthA = Classe::on('tenant')->create([
            'academic_year_id' => $yearOne->id,
            'level_id' => $sixthLevel->id,
            'section' => 'A',
        ]);
        $sixthB = Classe::on('tenant')->create([
            'academic_year_id' => $yearOne->id,
            'level_id' => $sixthLevel->id,
            'section' => 'B',
        ]);
        $fifthA = Classe::on('tenant')->create([
            'academic_year_id' => $yearTwo->id,
            'level_id' => $fifthLevel->id,
            'section' => 'A',
        ]);

        $currentEnrollment = ClassEnrollment::on('tenant')->create([
            'student_id' => $student->id,
            'classe_id' => $sixthA->id,
            'academic_year_id' => $yearOne->id,
            'enrolled_at' => '2026-10-01',
            'assignment_type' => 'Nouvelle inscription',
            'status' => 'Actif',
        ]);

        $this->expectException(QueryException::class);

        try {
            ClassEnrollment::on('tenant')->create([
                'student_id' => $student->id,
                'classe_id' => $sixthB->id,
                'academic_year_id' => $yearOne->id,
                'enrolled_at' => '2026-10-01',
                'assignment_type' => 'Nouvelle inscription',
                'status' => 'Actif',
            ]);
        } finally {
            ClassEnrollment::on('tenant')->create([
                'student_id' => $student->id,
                'classe_id' => $fifthA->id,
                'academic_year_id' => $yearTwo->id,
                'previous_class_enrollment_id' => $currentEnrollment->id,
                'enrolled_at' => '2027-10-01',
                'assignment_type' => 'Passage',
                'status' => 'Actif',
            ]);

            $this->assertDatabaseHas('class_enrollments', [
                'student_id' => $student->id,
                'classe_id' => $fifthA->id,
                'academic_year_id' => $yearTwo->id,
                'previous_class_enrollment_id' => $currentEnrollment->id,
                'assignment_type' => 'Passage',
            ], 'tenant');
        }
    }

    #[Test]
    public function it_supports_repeating_the_same_level_in_the_next_academic_year(): void
    {
        $student = Student::factory()->create();
        $sixthLevel = Level::on('tenant')->where('code', '6E')->firstOrFail();

        $yearOne = AcademicYear::on('tenant')->create([
            'name' => '2026-2027',
            'start_date' => '2026-10-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);
        $yearTwo = AcademicYear::on('tenant')->create([
            'name' => '2027-2028',
            'start_date' => '2027-10-01',
            'end_date' => '2028-06-30',
            'is_active' => false,
        ]);

        $previousClass = Classe::on('tenant')->create([
            'academic_year_id' => $yearOne->id,
            'level_id' => $sixthLevel->id,
            'section' => 'A',
        ]);
        $repeatClass = Classe::on('tenant')->create([
            'academic_year_id' => $yearTwo->id,
            'level_id' => $sixthLevel->id,
            'section' => 'A',
        ]);

        $previousEnrollment = ClassEnrollment::on('tenant')->create([
            'student_id' => $student->id,
            'classe_id' => $previousClass->id,
            'academic_year_id' => $yearOne->id,
            'enrolled_at' => '2026-10-01',
            'assignment_type' => 'Nouvelle inscription',
            'status' => 'Terminé',
            'final_decision' => 'Redoublement',
            'final_decision_at' => '2027-06-30',
        ]);

        $repeatEnrollment = ClassEnrollment::on('tenant')->create([
            'student_id' => $student->id,
            'classe_id' => $repeatClass->id,
            'academic_year_id' => $yearTwo->id,
            'previous_class_enrollment_id' => $previousEnrollment->id,
            'enrolled_at' => '2027-10-01',
            'assignment_type' => 'Redoublement',
            'status' => 'Actif',
        ]);

        $this->assertTrue($repeatEnrollment->isRepeat());
        $this->assertSame($previousEnrollment->id, $repeatEnrollment->previousClassEnrollment->id);
    }
}
