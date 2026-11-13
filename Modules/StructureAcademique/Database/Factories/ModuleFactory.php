<?php

namespace Modules\StructureAcademique\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\StructureAcademique\Entities\Module;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    protected $model = Module::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->regexify('MOD[0-9]{5}'),
            'name' => fake()->words(3, true),
            'credits_ects' => fake()->numberBetween(1, 6),
            'coefficient' => fake()->randomElement([1, 2, 3]),
            'type' => 'Obligatoire',
            'semester' => 'S1',
            'level' => 'L1',
            'description' => fake()->sentence(),
            'hours_cm' => 20,
            'hours_td' => 10,
            'hours_tp' => 0,
            'is_eliminatory' => false,
        ];
    }

    public function eliminatory(float $threshold = 7.0): static
    {
        return $this->state(fn (array $attributes) => [
            'is_eliminatory' => true,
            'eliminatory_threshold' => $threshold,
        ]);
    }
}
