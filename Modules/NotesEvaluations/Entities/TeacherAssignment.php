<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\NotesEvaluations\Database\Factories\TeacherAssignmentFactory;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Subject;
use Modules\UsersGuard\Entities\TenantUser;

/**
 * Notes lycée — professeur affecté à une matière d'une classe pour une année scolaire.
 */
class TeacherAssignment extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'teacher_assignments';

    protected $fillable = [
        'teacher_id',
        'classe_id',
        'subject_id',
        'academic_year_id',
    ];

    protected static function newFactory(): TeacherAssignmentFactory
    {
        return TeacherAssignmentFactory::new();
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'teacher_id');
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
