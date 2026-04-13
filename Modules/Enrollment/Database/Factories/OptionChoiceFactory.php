<?php

namespace Modules\Enrollment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Option;
use Modules\Enrollment\Entities\OptionChoice;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;

class OptionChoiceFactory extends Factory
{
    protected $model = OptionChoice::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'option_id' => Option::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'choice_rank' => $this->faker->numberBetween(1, 3),
            'status' => 'Pending',
            'motivation' => $this->faker->optional()->paragraph(),
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Pending',
        ]);
    }

    public function validated(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Validated',
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Rejected',
        ]);
    }

    public function firstChoice(): self
    {
        return $this->state(fn (array $attributes) => [
            'choice_rank' => 1,
        ]);
    }

    public function secondChoice(): self
    {
        return $this->state(fn (array $attributes) => [
            'choice_rank' => 2,
        ]);
    }

    public function thirdChoice(): self
    {
        return $this->state(fn (array $attributes) => [
            'choice_rank' => 3,
        ]);
    }

    public function withMotivation(?string $motivation = null): self
    {
        return $this->state(fn (array $attributes) => [
            'motivation' => $motivation ?? $this->faker->paragraph(),
        ]);
    }
}
