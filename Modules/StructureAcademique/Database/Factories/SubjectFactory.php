<?php

namespace Modules\StructureAcademique\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\StructureAcademique\Entities\Subject;
use Modules\StructureAcademique\Enums\SubjectCategory;

class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->regexify('[A-Z]{3,5}'),
            'name' => fake()->word(),
            'short_name' => fake()->word(),
            'category' => fake()->randomElement(SubjectCategory::cases()),
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
