<?php

namespace Modules\NotesEvaluations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\NotesEvaluations\Entities\CoefficientTemplate;

class CoefficientTemplateFactory extends Factory
{
    protected $model = CoefficientTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'evaluations' => [
                ['type' => 'CC', 'coefficient' => 1, 'max_score' => 20],
                ['type' => 'Examen', 'coefficient' => 2, 'max_score' => 20],
            ],
            'is_system' => false,
        ];
    }

    /**
     * State for system template
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    /**
     * State for standard LMD template
     */
    public function standardLmd(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Standard LMD',
            'description' => 'Template standard pour le système LMD',
            'evaluations' => [
                ['type' => 'CC', 'coefficient' => 1, 'max_score' => 20],
                ['type' => 'TP', 'coefficient' => 1, 'max_score' => 20],
                ['type' => 'Examen', 'coefficient' => 2, 'max_score' => 20],
            ],
            'is_system' => true,
        ]);
    }
}
