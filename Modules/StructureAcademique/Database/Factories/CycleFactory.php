<?php

namespace Modules\StructureAcademique\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\StructureAcademique\Entities\Cycle;

class CycleFactory extends Factory
{
    protected $model = Cycle::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->regexify('[A-Z]{3}'),
            'name' => fake()->word(),
            'description' => fake()->sentence(),
            'display_order' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function college(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'COL',
            'name' => 'Collège (1er cycle)',
            'description' => 'Enseignement du 1er cycle',
            'display_order' => 1,
        ]);
    }

    public function lycee(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'LYC',
            'name' => 'Lycée (2nd cycle)',
            'description' => 'Enseignement du 2nd cycle',
            'display_order' => 2,
        ]);
    }
}
