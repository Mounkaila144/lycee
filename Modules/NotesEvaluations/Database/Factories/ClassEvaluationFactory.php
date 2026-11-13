<?php

namespace Modules\NotesEvaluations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\NotesEvaluations\Entities\ClassEvaluation;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\Subject;
use Modules\UsersGuard\Entities\TenantUser;

/**
 * @extends Factory<ClassEvaluation>
 */
class ClassEvaluationFactory extends Factory
{
    protected $model = ClassEvaluation::class;

    public function definition(): array
    {
        return [
            'classe_id' => Classe::factory(),
            'subject_id' => Subject::factory(),
            'semester_id' => Semester::factory(),
            'teacher_id' => TenantUser::factory(),
            'title' => 'Devoir '.fake()->numberBetween(1, 5),
            'type' => 'devoir',
            'evaluation_date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'max_score' => 20,
            'weight' => 1,
            'status' => ClassEvaluation::STATUS_DRAFT,
            'created_by' => fn (array $attributes) => $attributes['teacher_id'],
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClassEvaluation::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ClassEvaluation::STATUS_PUBLISHED,
            'submitted_at' => now(),
            'published_at' => now(),
        ]);
    }

    public function composition(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'composition',
            'title' => 'Composition',
            'weight' => 2,
        ]);
    }
}
