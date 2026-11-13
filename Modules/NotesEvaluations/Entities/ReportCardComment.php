<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Database\Factories\ReportCardCommentFactory;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Entities\Subject;
use Modules\UsersGuard\Entities\TenantUser;

/**
 * Notes lycée — appréciation du bulletin (matière, ou générale si subject_id est NULL).
 */
class ReportCardComment extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'report_card_comments';

    protected $fillable = [
        'student_id',
        'semester_id',
        'subject_id',
        'comment',
        'author_id',
    ];

    protected static function newFactory(): ReportCardCommentFactory
    {
        return ReportCardCommentFactory::new();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'author_id');
    }
}
