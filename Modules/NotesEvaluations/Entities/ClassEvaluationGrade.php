<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Database\Factories\ClassEvaluationGradeFactory;
use Modules\UsersGuard\Entities\TenantUser;

/**
 * Notes lycée — note d'un élève à une évaluation de classe.
 */
class ClassEvaluationGrade extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'class_evaluation_grades';

    protected $fillable = [
        'class_evaluation_id',
        'student_id',
        'score',
        'is_absent',
        'comment',
        'entered_by',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'is_absent' => 'boolean',
        ];
    }

    protected static function newFactory(): ClassEvaluationGradeFactory
    {
        return ClassEvaluationGradeFactory::new();
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ClassEvaluation::class, 'class_evaluation_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'entered_by');
    }
}
