<?php

namespace Modules\NotesEvaluations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\UsersGuard\Entities\User;

class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'evaluation_id' => ModuleEvaluationConfig::factory(),
            'score' => $this->faker->randomFloat(2, 0, 20),
            'is_absent' => false,
            'comment' => $this->faker->optional(0.3)->sentence(),
            'entered_by' => User::factory(),
            'entered_at' => now(),
            'status' => 'Draft',
            'is_visible_to_students' => false,
            'published_at' => null,
        ];
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_absent' => true,
            'score' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Published',
            'is_visible_to_students' => true,
            'published_at' => now(),
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Submitted',
        ]);
    }

    public function validated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Validated',
        ]);
    }

    public function passing(): static
    {
        return $this->state(fn (array $attributes) => [
            'score' => $this->faker->randomFloat(2, 10, 20),
        ]);
    }

    public function failing(): static
    {
        return $this->state(fn (array $attributes) => [
            'score' => $this->faker->randomFloat(2, 0, 9.99),
        ]);
    }
}
