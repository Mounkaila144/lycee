<?php

namespace Modules\Enrollment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Option;
use Modules\StructureAcademique\Entities\Programme;

class OptionFactory extends Factory
{
    protected $model = Option::class;

    public function definition(): array
    {
        $levels = ['L1', 'L2', 'L3', 'M1', 'M2'];
        $startDate = $this->faker->dateTimeBetween('now', '+1 month');
        $endDate = $this->faker->dateTimeBetween($startDate, '+3 months');

        return [
            'programme_id' => Programme::factory(),
            'level' => $this->faker->randomElement($levels),
            'code' => strtoupper($this->faker->unique()->lexify('OPT-????')),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'capacity' => $this->faker->numberBetween(20, 50),
            'prerequisites' => null,
            'is_mandatory' => $this->faker->boolean(20),
            'choice_start_date' => $startDate,
            'choice_end_date' => $endDate,
            'status' => 'Open',
        ];
    }

    public function open(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Open',
        ]);
    }

    public function closed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Closed',
        ]);
    }

    public function archived(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Archived',
        ]);
    }

    public function mandatory(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_mandatory' => true,
        ]);
    }

    public function optional(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_mandatory' => false,
        ]);
    }

    public function forLevel(string $level): self
    {
        return $this->state(fn (array $attributes) => [
            'level' => $level,
        ]);
    }

    public function withCapacity(int $capacity): self
    {
        return $this->state(fn (array $attributes) => [
            'capacity' => $capacity,
        ]);
    }

    public function withPrerequisites(array $prerequisites): self
    {
        return $this->state(fn (array $attributes) => [
            'prerequisites' => $prerequisites,
        ]);
    }

    public function choicePeriodActive(): self
    {
        return $this->state(fn (array $attributes) => [
            'choice_start_date' => now()->subDays(7),
            'choice_end_date' => now()->addDays(30),
            'status' => 'Open',
        ]);
    }

    public function choicePeriodExpired(): self
    {
        return $this->state(fn (array $attributes) => [
            'choice_start_date' => now()->subMonths(2),
            'choice_end_date' => now()->subDays(7),
            'status' => 'Closed',
        ]);
    }

    public function choicePeriodNotStarted(): self
    {
        return $this->state(fn (array $attributes) => [
            'choice_start_date' => now()->addDays(7),
            'choice_end_date' => now()->addMonths(2),
            'status' => 'Open',
        ]);
    }
}
