<?php

namespace Modules\NotesEvaluations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\ReportCardComment;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\TenantUser;

/**
 * @extends Factory<ReportCardComment>
 */
class ReportCardCommentFactory extends Factory
{
    protected $model = ReportCardComment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'semester_id' => Semester::factory(),
            'subject_id' => null,
            'comment' => fake()->sentence(),
            'author_id' => TenantUser::factory(),
        ];
    }
}
