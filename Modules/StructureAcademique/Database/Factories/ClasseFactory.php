<?php

namespace Modules\StructureAcademique\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;

class ClasseFactory extends Factory
{
    protected $model = Classe::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'level_id' => Level::factory(),
            'series_id' => null,
            'section' => fake()->randomElement(['A', 'B', 'C', '1', '2']),
            'name' => fake()->unique()->word(),
            'max_capacity' => fake()->numberBetween(30, 80),
            'classroom' => 'Salle ' . fake()->numberBetween(1, 20),
            'head_teacher_id' => null,
        ];
    }
}
