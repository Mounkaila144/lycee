<?php

namespace Modules\StructureAcademique\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\StructureAcademique\Entities\Cycle;
use Modules\StructureAcademique\Entities\Level;

class LevelFactory extends Factory
{
    protected $model = Level::class;

    public function definition(): array
    {
        return [
            'cycle_id' => Cycle::factory(),
            'code' => fake()->unique()->regexify('[A-Z0-9]{3}'),
            'name' => fake()->word(),
            'order_index' => fake()->numberBetween(1, 10),
        ];
    }
}
