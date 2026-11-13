<?php

namespace Modules\Enrollment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\PedagogicalEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;

/**
 * Factory aligned with the `student_enrollments` table used by PedagogicalEnrollment.
 *
 * @extends Factory<PedagogicalEnrollment>
 */
class PedagogicalEnrollmentFactory extends Factory
{
    protected $model = PedagogicalEnrollment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'programme_id' => Programme::factory(),
            'level' => $this->faker->randomElement(['6E', '5E', '4E', '3E', '2NDE', '1ERE', 'TLE']),
            'academic_year_id' => AcademicYear::factory(),
            'semester_id' => Semester::factory(),
            'enrollment_date' => now()->toDateString(),
            'status' => PedagogicalEnrollment::STATUS_ACTIVE,
            'notes' => null,
            'enrolled_by' => null,
        ];
    }

    /**
     * Enrollment awaiting validation (status "Actif").
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PedagogicalEnrollment::STATUS_ACTIVE,
        ]);
    }

    public function validated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PedagogicalEnrollment::STATUS_VALIDATED,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PedagogicalEnrollment::STATUS_REJECTED,
            'notes' => $this->faker->sentence(),
        ]);
    }

    public function withSemester(): static
    {
        return $this->state(fn (array $attributes) => [
            'semester_id' => Semester::factory(),
        ]);
    }
}
