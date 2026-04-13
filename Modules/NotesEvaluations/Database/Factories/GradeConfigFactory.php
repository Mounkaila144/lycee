<?php

namespace Modules\NotesEvaluations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\NotesEvaluations\Entities\GradeConfig;

class GradeConfigFactory extends Factory
{
    protected $model = GradeConfig::class;

    public function definition(): array
    {
        return [
            'min_module_average' => 10.00,
            'min_semester_average' => 10.00,
            'compensation_enabled' => true,
            'eliminatory_threshold' => 10.00,
            'allow_eliminatory_compensation' => false,
            'year_progression_threshold' => 80,
        ];
    }

    /**
     * State with compensation disabled
     */
    public function noCompensation(): static
    {
        return $this->state(fn (array $attributes) => [
            'compensation_enabled' => false,
        ]);
    }

    /**
     * State with eliminatory compensation allowed
     */
    public function allowEliminatoryCompensation(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_eliminatory_compensation' => true,
        ]);
    }

    /**
     * State with strict progression
     */
    public function strictProgression(): static
    {
        return $this->state(fn (array $attributes) => [
            'year_progression_threshold' => 100,
        ]);
    }
}
