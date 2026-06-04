<?php

namespace Modules\StructureAcademique\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\UsersGuard\Entities\TenantUser;

/**
 * A single teaching assignment: one teacher teaches one subject in one class.
 */
class TeacherAssignment extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'teacher_assignments';

    protected $fillable = [
        'teacher_id',
        'class_id',
        'subject_id',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'teacher_id');
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function scopeForTeacher(Builder $query, int $teacherId): Builder
    {
        return $query->where('teacher_id', $teacherId);
    }
}
