<?php

namespace Modules\NotesEvaluations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\NotesEvaluations\Entities\ModuleResult;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Semester;

class ModuleResultFactory extends Factory
{
    protected $model = ModuleResult::class;

    public function definition(): array
    {
        $totalStudents = $this->faker->numberBetween(20, 60);
        $passRate = $this->faker->randomFloat(2, 40, 95);
        $absenceRate = $this->faker->randomFloat(2, 0, 15);

        return [
            'module_id' => Module::factory(),
            'semester_id' => Semester::factory(),
            'total_students' => $totalStudents,
            'class_average' => $this->faker->randomFloat(2, 8, 15),
            'min_grade' => $this->faker->randomFloat(2, 0, 6),
            'max_grade' => $this->faker->randomFloat(2, 16, 20),
            'median' => $this->faker->randomFloat(2, 9, 13),
            'standard_deviation' => $this->faker->randomFloat(2, 2, 5),
            'pass_rate' => $passRate,
            'absence_rate' => $absenceRate,
            'distribution' => $this->generateDistribution($totalStudents),
            'generated_at' => now(),
            'published_at' => null,
        ];
    }

    /**
     * Generate realistic distribution data
     */
    private function generateDistribution(int $totalStudents): array
    {
        $remaining = $totalStudents;

        $bracket1 = (int) floor($totalStudents * 0.1); // 0-5
        $remaining -= $bracket1;

        $bracket2 = (int) floor($totalStudents * 0.25); // 5-10
        $remaining -= $bracket2;

        $bracket3 = (int) floor($totalStudents * 0.40); // 10-15
        $remaining -= $bracket3;

        $bracket4 = $remaining; // 15-20

        return [
            '0-5' => $bracket1,
            '5-10' => $bracket2,
            '10-15' => $bracket3,
            '15-20' => $bracket4,
        ];
    }

    /**
     * State for published result
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now(),
        ]);
    }

    /**
     * State for unpublished result
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => null,
        ]);
    }

    /**
     * State for high pass rate
     */
    public function highPassRate(): static
    {
        return $this->state(fn (array $attributes) => [
            'pass_rate' => $this->faker->randomFloat(2, 80, 95),
            'class_average' => $this->faker->randomFloat(2, 12, 15),
        ]);
    }

    /**
     * State for low pass rate
     */
    public function lowPassRate(): static
    {
        return $this->state(fn (array $attributes) => [
            'pass_rate' => $this->faker->randomFloat(2, 30, 50),
            'class_average' => $this->faker->randomFloat(2, 7, 10),
        ]);
    }
}
