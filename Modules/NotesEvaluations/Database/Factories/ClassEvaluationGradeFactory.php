<?php

namespace Modules\NotesEvaluations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\ClassEvaluation;
use Modules\NotesEvaluations\Entities\ClassEvaluationGrade;
use Modules\UsersGuard\Entities\TenantUser;

/**
 * @extends Factory<ClassEvaluationGrade>
 */
class ClassEvaluationGradeFactory extends Factory
{
    protected $model = ClassEvaluationGrade::class;

    public function definition(): array
    {
        return [
            'class_evaluation_id' => ClassEvaluation::factory(),
            'student_id' => Student::factory(),
            'score' => fake()->randomFloat(2, 0, 20),
            'is_absent' => false,
            'comment' => null,
            'entered_by' => TenantUser::factory(),
        ];
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_absent' => true,
            'score' => null,
        ]);
    }
}
