<?php

namespace Modules\StructureAcademique\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\StructureAcademique\Entities\AcademicYear;

class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        $startYear = fake()->numberBetween(2024, 2030);
        $endYear = $startYear + 1;

        return [
            'name' => "{$startYear}-{$endYear}",
            'start_date' => "{$startYear}-10-01",
            'end_date' => "{$endYear}-06-30",
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
