<?php

namespace Modules\Enrollment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Option;
use Modules\Enrollment\Entities\OptionAssignment;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\UsersGuard\Entities\User;

class OptionAssignmentFactory extends Factory
{
    protected $model = OptionAssignment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'option_id' => Option::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'choice_rank_obtained' => $this->faker->numberBetween(1, 3),
            'assignment_method' => 'Automatic',
            'assigned_by' => null,
            'assignment_notes' => null,
            'assigned_at' => now(),
        ];
    }

    public function automatic(): self
    {
        return $this->state(fn (array $attributes) => [
            'assignment_method' => 'Automatic',
            'assigned_by' => null,
        ]);
    }

    public function manual(): self
    {
        return $this->state(fn (array $attributes) => [
            'assignment_method' => 'Manual',
            'assigned_by' => User::factory(),
        ]);
    }

    public function firstChoiceObtained(): self
    {
        return $this->state(fn (array $attributes) => [
            'choice_rank_obtained' => 1,
        ]);
    }

    public function secondChoiceObtained(): self
    {
        return $this->state(fn (array $attributes) => [
            'choice_rank_obtained' => 2,
        ]);
    }

    public function thirdChoiceObtained(): self
    {
        return $this->state(fn (array $attributes) => [
            'choice_rank_obtained' => 3,
        ]);
    }

    public function withNotes(?string $notes = null): self
    {
        return $this->state(fn (array $attributes) => [
            'assignment_notes' => $notes ?? $this->faker->sentence(),
        ]);
    }

    public function assignedBy(User $user): self
    {
        return $this->state(fn (array $attributes) => [
            'assignment_method' => 'Manual',
            'assigned_by' => $user->id,
        ]);
    }
}
