<?php

namespace Modules\Enrollment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Group;
use Modules\Enrollment\Entities\GroupAssignment;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Module;

class GroupAssignmentFactory extends Factory
{
    protected $model = GroupAssignment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'group_id' => Group::factory(),
            'module_id' => Module::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'assignment_method' => $this->faker->randomElement(['Automatic', 'Manual']),
            'assigned_by' => null,
            'assignment_reason' => $this->faker->optional()->sentence(),
            'assigned_at' => now(),
        ];
    }

    public function automatic(): self
    {
        return $this->state(fn (array $attributes) => [
            'assignment_method' => 'Automatic',
        ]);
    }

    public function manual(): self
    {
        return $this->state(fn (array $attributes) => [
            'assignment_method' => 'Manual',
        ]);
    }

    public function forStudent(Student $student): self
    {
        return $this->state(fn (array $attributes) => [
            'student_id' => $student->id,
        ]);
    }

    public function forGroup(Group $group): self
    {
        return $this->state(fn (array $attributes) => [
            'group_id' => $group->id,
            'module_id' => $group->module_id,
            'academic_year_id' => $group->academic_year_id,
        ]);
    }

    public function forModule(Module $module): self
    {
        return $this->state(fn (array $attributes) => [
            'module_id' => $module->id,
        ]);
    }

    public function forAcademicYear(AcademicYear $academicYear): self
    {
        return $this->state(fn (array $attributes) => [
            'academic_year_id' => $academicYear->id,
        ]);
    }

    public function assignedBy($userId): self
    {
        return $this->state(fn (array $attributes) => [
            'assigned_by' => $userId,
        ]);
    }

    public function withReason(string $reason): self
    {
        return $this->state(fn (array $attributes) => [
            'assignment_reason' => $reason,
        ]);
    }
}
