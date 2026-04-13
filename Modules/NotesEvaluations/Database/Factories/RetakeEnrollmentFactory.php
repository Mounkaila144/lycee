<?php

namespace Modules\NotesEvaluations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\RetakeEnrollment;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Semester;

class RetakeEnrollmentFactory extends Factory
{
    protected $model = RetakeEnrollment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'module_id' => Module::factory(),
            'semester_id' => Semester::factory(),
            'original_average' => $this->faker->randomFloat(2, 0, 9.99),
            'status' => 'pending',
            'identified_at' => now(),
        ];
    }

    /**
     * State for pending retake
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'scheduled_at' => null,
        ]);
    }

    /**
     * State for scheduled retake
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'scheduled_at' => now()->addDays($this->faker->numberBetween(7, 30)),
        ]);
    }

    /**
     * State for graded retake
     */
    public function graded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'graded',
        ]);
    }

    /**
     * State for validated retake
     */
    public function validated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'validated',
        ]);
    }

    /**
     * State for cancelled retake
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * State for retake with very low original average
     */
    public function veryLow(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_average' => $this->faker->randomFloat(2, 0, 5),
        ]);
    }

    /**
     * State for retake with borderline original average
     */
    public function borderline(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_average' => $this->faker->randomFloat(2, 7, 9.99),
        ]);
    }
}
