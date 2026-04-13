<?php

namespace Modules\NotesEvaluations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Semester;

class ModuleGradeFactory extends Factory
{
    protected $model = ModuleGrade::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'module_id' => Module::factory(),
            'semester_id' => Semester::factory(),
            'average' => $this->faker->randomFloat(2, 0, 20),
            'is_final' => $this->faker->boolean(70),
            'missing_evaluations_count' => $this->faker->numberBetween(0, 3),
            'status' => $this->faker->randomElement(['Provisoire', 'Final', 'ABS']),
            'calculated_at' => now(),
        ];
    }

    /**
     * State for final grade
     */
    public function final(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_final' => true,
            'missing_evaluations_count' => 0,
            'status' => 'Final',
        ]);
    }

    /**
     * State for provisional grade
     */
    public function provisional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_final' => false,
            'status' => 'Provisoire',
        ]);
    }

    /**
     * State for absent
     */
    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'average' => null,
            'is_final' => true,
            'status' => 'ABS',
        ]);
    }

    /**
     * State for validated grade
     */
    public function validated(): static
    {
        return $this->state(fn (array $attributes) => [
            'average' => $this->faker->randomFloat(2, 10, 20),
            'is_final' => true,
            'status' => 'Final',
        ]);
    }

    /**
     * State for not validated grade
     */
    public function notValidated(): static
    {
        return $this->state(fn (array $attributes) => [
            'average' => $this->faker->randomFloat(2, 0, 9.99),
            'is_final' => true,
            'status' => 'Final',
        ]);
    }
}
