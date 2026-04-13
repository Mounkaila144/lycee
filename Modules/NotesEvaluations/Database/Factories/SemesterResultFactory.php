<?php

namespace Modules\NotesEvaluations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\SemesterResult;
use Modules\StructureAcademique\Entities\Semester;

class SemesterResultFactory extends Factory
{
    protected $model = SemesterResult::class;

    public function definition(): array
    {
        $totalCredits = 30;
        $acquiredCredits = $this->faker->numberBetween(0, 30);

        return [
            'student_id' => Student::factory(),
            'semester_id' => Semester::factory(),
            'average' => $this->faker->randomFloat(2, 0, 20),
            'is_final' => $this->faker->boolean(70),
            'is_validated' => false,
            'validation_blocked_by_eliminatory' => false,
            'blocking_reasons' => null,
            'total_credits' => $totalCredits,
            'acquired_credits' => $acquiredCredits,
            'missing_credits' => $totalCredits - $acquiredCredits,
            'success_rate' => round(($acquiredCredits / $totalCredits) * 100, 2),
            'missing_modules_count' => 0,
            'calculated_at' => now(),
            'published_at' => null,
        ];
    }

    /**
     * State for final result
     */
    public function final(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_final' => true,
            'missing_modules_count' => 0,
        ]);
    }

    /**
     * State for published result
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_final' => true,
            'published_at' => now(),
        ]);
    }

    /**
     * State for validated semester
     */
    public function validated(): static
    {
        return $this->state(fn (array $attributes) => [
            'average' => $this->faker->randomFloat(2, 10, 20),
            'is_final' => true,
            'is_validated' => true,
            'acquired_credits' => 30,
            'missing_credits' => 0,
            'success_rate' => 100,
        ]);
    }

    /**
     * State for not validated semester
     */
    public function notValidated(): static
    {
        return $this->state(fn (array $attributes) => [
            'average' => $this->faker->randomFloat(2, 0, 9.99),
            'is_final' => true,
            'is_validated' => false,
        ]);
    }

    /**
     * State for blocked by eliminatory
     */
    public function blockedByEliminatory(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_final' => true,
            'is_validated' => false,
            'validation_blocked_by_eliminatory' => true,
            'blocking_reasons' => ['Module éliminatoire non validé'],
        ]);
    }
}
