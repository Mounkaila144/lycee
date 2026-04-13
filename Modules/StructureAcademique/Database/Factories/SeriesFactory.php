<?php

namespace Modules\StructureAcademique\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\StructureAcademique\Entities\Series;

class SeriesFactory extends Factory
{
    protected $model = Series::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->regexify('[A-Z]{1,3}'),
            'name' => fake()->word(),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
