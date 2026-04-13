<?php

namespace Modules\NotesEvaluations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\EctsAllocation;
use Modules\NotesEvaluations\Entities\SemesterResult;
use Modules\StructureAcademique\Entities\Module;

class EctsAllocationFactory extends Factory
{
    protected $model = EctsAllocation::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'module_id' => Module::factory(),
            'semester_result_id' => SemesterResult::factory(),
            'credits_allocated' => $this->faker->numberBetween(2, 6),
            'allocation_type' => $this->faker->randomElement(['validated', 'compensated']),
            'note' => null,
            'allocated_at' => now(),
        ];
    }

    /**
     * State for validated allocation
     */
    public function validated(): static
    {
        return $this->state(fn (array $attributes) => [
            'allocation_type' => EctsAllocation::TYPE_VALIDATED,
        ]);
    }

    /**
     * State for compensated allocation
     */
    public function compensated(): static
    {
        return $this->state(fn (array $attributes) => [
            'allocation_type' => EctsAllocation::TYPE_COMPENSATED,
        ]);
    }

    /**
     * State for equivalence allocation
     */
    public function equivalence(): static
    {
        return $this->state(fn (array $attributes) => [
            'allocation_type' => EctsAllocation::TYPE_EQUIVALENCE,
            'semester_result_id' => null,
            'note' => 'Équivalence accordée',
        ]);
    }
}
