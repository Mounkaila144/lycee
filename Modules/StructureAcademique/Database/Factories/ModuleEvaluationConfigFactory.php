<?php

namespace Modules\StructureAcademique\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\StructureAcademique\Entities\Semester;

/**
 * @extends Factory<ModuleEvaluationConfig>
 */
class ModuleEvaluationConfigFactory extends Factory
{
    protected $model = ModuleEvaluationConfig::class;

    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'semester_id' => fn (): int => Semester::query()->value('id') ?? Semester::factory()->create()->id,
            'name' => fake()->randomElement(['Devoir 1', 'Devoir 2', 'Composition']),
            'type' => fake()->randomElement(['CC', 'Examen']),
            'coefficient' => 1,
            'max_score' => 20,
            'planned_date' => now()->addWeek()->toDateString(),
            'is_eliminatory' => false,
            'elimination_threshold' => null,
            'order' => 1,
            'status' => 'Published',
        ];
    }
}
