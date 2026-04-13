<?php

namespace Modules\StructureAcademique\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Entities\Subject;
use Modules\StructureAcademique\Entities\SubjectClassCoefficient;

class SubjectClassCoefficientFactory extends Factory
{
    protected $model = SubjectClassCoefficient::class;

    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'level_id' => Level::factory(),
            'series_id' => null,
            'coefficient' => fake()->randomFloat(1, 1, 8),
            'hours_per_week' => fake()->numberBetween(1, 10),
        ];
    }
}
