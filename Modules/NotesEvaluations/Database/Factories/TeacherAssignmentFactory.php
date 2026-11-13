<?php

namespace Modules\NotesEvaluations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\NotesEvaluations\Entities\TeacherAssignment;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Subject;
use Modules\UsersGuard\Entities\TenantUser;

/**
 * @extends Factory<TeacherAssignment>
 */
class TeacherAssignmentFactory extends Factory
{
    protected $model = TeacherAssignment::class;

    public function definition(): array
    {
        return [
            'teacher_id' => TenantUser::factory(),
            'classe_id' => Classe::factory(),
            'subject_id' => Subject::factory(),
            'academic_year_id' => fn (array $attributes) => Classe::query()->find($attributes['classe_id'])?->academic_year_id,
        ];
    }
}
