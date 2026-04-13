<?php

namespace Modules\NotesEvaluations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\NotesEvaluations\Entities\GradeValidation;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\UsersGuard\Entities\User;

class GradeValidationFactory extends Factory
{
    protected $model = GradeValidation::class;

    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'evaluation_id' => ModuleEvaluationConfig::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'submitted_by' => User::factory(),
            'status' => 'Pending',
            'submitted_at' => now(),
            'statistics' => [
                'count' => 25,
                'average' => 12.5,
                'std_dev' => 3.2,
                'min' => 4.0,
                'max' => 19.0,
                'pass_rate' => 72.0,
            ],
            'anomalies' => [],
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Approved',
            'validated_by' => User::factory(),
            'validated_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Rejected',
            'validated_by' => User::factory(),
            'validated_at' => now(),
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Published',
            'validated_by' => User::factory(),
            'validated_at' => now(),
            'published_at' => now(),
        ]);
    }
}
