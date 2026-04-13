<?php

namespace Modules\NotesEvaluations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\NotesEvaluations\Entities\RetakeEnrollment;
use Modules\NotesEvaluations\Entities\RetakeGrade;
use Modules\UsersGuard\Entities\User;

class RetakeGradeFactory extends Factory
{
    protected $model = RetakeGrade::class;

    public function definition(): array
    {
        return [
            'retake_enrollment_id' => RetakeEnrollment::factory(),
            'score' => $this->faker->randomFloat(2, 0, 20),
            'is_absent' => false,
            'entered_by' => User::factory(),
            'entered_at' => now(),
            'status' => 'draft',
        ];
    }

    /**
     * State for draft grade
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    /**
     * State for submitted grade
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    /**
     * State for validated grade
     */
    public function validated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'validated',
            'submitted_at' => now()->subDay(),
            'validated_at' => now(),
        ]);
    }

    /**
     * State for published grade
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'submitted_at' => now()->subDays(2),
            'validated_at' => now()->subDay(),
            'published_at' => now(),
        ]);
    }

    /**
     * State for absent
     */
    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'score' => null,
            'is_absent' => true,
        ]);
    }

    /**
     * State for passing grade
     */
    public function passing(): static
    {
        return $this->state(fn (array $attributes) => [
            'score' => $this->faker->randomFloat(2, 10, 20),
            'is_absent' => false,
        ]);
    }

    /**
     * State for failing grade
     */
    public function failing(): static
    {
        return $this->state(fn (array $attributes) => [
            'score' => $this->faker->randomFloat(2, 0, 9.99),
            'is_absent' => false,
        ]);
    }
}
