<?php

namespace Modules\Enrollment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Group;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;

class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        $types = ['CM', 'TD', 'TP'];
        $levels = ['L1', 'L2', 'L3', 'M1', 'M2'];
        $type = $this->faker->randomElement($types);
        $level = $this->faker->randomElement($levels);

        return [
            'module_id' => Module::factory(),
            'program_id' => Programme::factory(),
            'level' => $level,
            'academic_year_id' => AcademicYear::factory(),
            'semester_id' => Semester::factory(),
            'code' => strtoupper($this->faker->unique()->lexify('GRP-???-').$type.'-'.$this->faker->numerify('##')),
            'name' => 'Groupe '.$type.' '.$this->faker->numerify('#'),
            'type' => $type,
            'capacity_min' => 20,
            'capacity_max' => 35,
            'teacher_id' => null,
            'room_id' => 'S'.$this->faker->numberBetween(1, 20),
            'status' => 'Active',
        ];
    }

    public function cm(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'CM',
            'capacity_max' => 100,
        ]);
    }

    public function td(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'TD',
            'capacity_max' => 35,
        ]);
    }

    public function tp(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'TP',
            'capacity_max' => 20,
        ]);
    }

    public function active(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Active',
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Inactive',
        ]);
    }

    public function withTeacher($teacherId = null): self
    {
        return $this->state(fn (array $attributes) => [
            'teacher_id' => $teacherId ?? \Modules\UsersGuard\Entities\User::factory(),
        ]);
    }

    public function forModule(Module $module): self
    {
        return $this->state(fn (array $attributes) => [
            'module_id' => $module->id,
            'level' => $module->level,
        ]);
    }

    public function forProgramme(Programme $programme): self
    {
        return $this->state(fn (array $attributes) => [
            'program_id' => $programme->id,
        ]);
    }

    public function forAcademicYear(AcademicYear $academicYear): self
    {
        return $this->state(fn (array $attributes) => [
            'academic_year_id' => $academicYear->id,
        ]);
    }

    public function forSemester(Semester $semester): self
    {
        return $this->state(fn (array $attributes) => [
            'semester_id' => $semester->id,
            'academic_year_id' => $semester->academic_year_id,
        ]);
    }

    public function forLevel(string $level): self
    {
        return $this->state(fn (array $attributes) => [
            'level' => $level,
        ]);
    }
}
