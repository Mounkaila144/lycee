<?php

namespace Modules\Enrollment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\PedagogicalEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;

class PedagogicalEnrollmentFactory extends Factory
{
    protected $model = PedagogicalEnrollment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'program_id' => Programme::factory(),
            'level' => $this->faker->randomElement(['L1', 'L2', 'L3', 'M1', 'M2']),
            'academic_year_id' => AcademicYear::factory(),
            'semester_id' => null,
            'status' => PedagogicalEnrollment::STATUS_DRAFT,
            'total_modules' => $this->faker->numberBetween(5, 10),
            'total_ects' => $this->faker->numberBetween(25, 35),
            'modules_check' => false,
            'groups_check' => false,
            'options_check' => false,
            'prerequisites_check' => false,
            'validated_by' => null,
            'validated_at' => null,
            'rejection_reason' => null,
            'contract_pdf_path' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PedagogicalEnrollment::STATUS_DRAFT,
        ]);
    }

    public function complete(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PedagogicalEnrollment::STATUS_COMPLETE,
            'modules_check' => true,
            'groups_check' => true,
            'options_check' => true,
            'prerequisites_check' => true,
            'total_ects' => 30,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PedagogicalEnrollment::STATUS_ACTIVE,
            'modules_check' => true,
            'groups_check' => true,
            'options_check' => true,
            'prerequisites_check' => true,
            'total_ects' => 30,
        ]);
    }

    public function validated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PedagogicalEnrollment::STATUS_VALIDATED,
            'modules_check' => true,
            'groups_check' => true,
            'options_check' => true,
            'prerequisites_check' => true,
            'total_ects' => 30,
            'validated_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PedagogicalEnrollment::STATUS_REJECTED,
            'rejection_reason' => $this->faker->sentence(),
            'validated_at' => now(),
        ]);
    }

    public function withSemester(): static
    {
        return $this->state(fn (array $attributes) => [
            'semester_id' => Semester::factory(),
        ]);
    }
}
