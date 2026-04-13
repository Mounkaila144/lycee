<?php

namespace Modules\Enrollment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentEnrollment;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;

class StudentEnrollmentFactory extends Factory
{
    protected $model = StudentEnrollment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'programme_id' => Programme::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'semester_id' => Semester::factory(),
            'level' => $this->faker->randomElement(['L1', 'L2', 'L3', 'M1', 'M2']),
            'group_id' => null,
            'enrollment_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'status' => 'Actif',
            'notes' => null,
            'enrolled_by' => null,
        ];
    }

    public function active(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Actif',
        ]);
    }

    public function suspended(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Suspendu',
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Annulé',
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Terminé',
        ]);
    }

    public function l1(): self
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'L1',
        ]);
    }

    public function l2(): self
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'L2',
        ]);
    }

    public function l3(): self
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'L3',
        ]);
    }

    public function m1(): self
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'M1',
        ]);
    }

    public function m2(): self
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'M2',
        ]);
    }

    public function forStudent(Student $student): self
    {
        return $this->state(fn (array $attributes) => [
            'student_id' => $student->id,
        ]);
    }

    public function forProgramme(Programme $programme): self
    {
        return $this->state(fn (array $attributes) => [
            'programme_id' => $programme->id,
        ]);
    }

    public function forSemester(Semester $semester): self
    {
        return $this->state(fn (array $attributes) => [
            'semester_id' => $semester->id,
            'academic_year_id' => $semester->academic_year_id,
        ]);
    }
}
