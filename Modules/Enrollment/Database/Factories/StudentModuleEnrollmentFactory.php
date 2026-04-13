<?php

namespace Modules\Enrollment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentEnrollment;
use Modules\Enrollment\Entities\StudentModuleEnrollment;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Semester;

class StudentModuleEnrollmentFactory extends Factory
{
    protected $model = StudentModuleEnrollment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'student_enrollment_id' => StudentEnrollment::factory(),
            'module_id' => Module::factory(),
            'semester_id' => Semester::factory(),
            'enrollment_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'status' => 'Inscrit',
            'is_optional' => false,
            'notes' => null,
            'enrolled_by' => null,
        ];
    }

    public function inscrit(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Inscrit',
        ]);
    }

    public function valide(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Validé',
        ]);
    }

    public function nonValide(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Non validé',
        ]);
    }

    public function abandonne(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Abandonné',
        ]);
    }

    public function dispense(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Dispensé',
        ]);
    }

    public function optional(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_optional' => true,
        ]);
    }

    public function obligatoire(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_optional' => false,
        ]);
    }

    public function forStudent(Student $student): self
    {
        return $this->state(fn (array $attributes) => [
            'student_id' => $student->id,
        ]);
    }

    public function forEnrollment(StudentEnrollment $enrollment): self
    {
        return $this->state(fn (array $attributes) => [
            'student_enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'semester_id' => $enrollment->semester_id,
        ]);
    }

    public function forModule(Module $module): self
    {
        return $this->state(fn (array $attributes) => [
            'module_id' => $module->id,
            'is_optional' => $module->type === 'Optionnel',
        ]);
    }
}
