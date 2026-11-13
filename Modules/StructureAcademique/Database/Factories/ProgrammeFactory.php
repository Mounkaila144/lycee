<?php

namespace Modules\StructureAcademique\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\StructureAcademique\Entities\Programme;

/**
 * @extends Factory<Programme>
 */
class ProgrammeFactory extends Factory
{
    protected $model = Programme::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->regexify('PRG[A-Z0-9]{6}'),
            'libelle' => 'Programme '.fake()->words(2, true),
            'type' => 'Licence',
            'duree_annees' => 3,
            'description' => fake()->sentence(),
            'responsable_id' => null,
            'statut' => 'Actif',
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'Brouillon',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'Inactif',
        ]);
    }
}
