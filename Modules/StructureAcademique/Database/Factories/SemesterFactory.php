<?php

namespace Modules\StructureAcademique\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Semester;

class SemesterFactory extends Factory
{
    protected $model = Semester::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'name' => fake()->randomElement(['S1', 'S2']),
            'start_date' => '2025-10-01',
            'end_date' => '2026-02-28',
            'is_active' => false,
        ];
    }

    public function s1(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'S1',
            'start_date' => '2025-10-01',
            'end_date' => '2026-02-28',
        ]);
    }

    public function s2(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'S2',
            'start_date' => '2026-03-01',
            'end_date' => '2026-06-30',
        ]);
    }
}
