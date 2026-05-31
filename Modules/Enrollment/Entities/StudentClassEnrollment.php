<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;

/**
 * One row per student per academic year — the class enrollment history that
 * powers re-enrollment (promotion to the next level) and per-year rosters.
 */
class StudentClassEnrollment extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'student_class_enrollments';

    protected $fillable = [
        'student_id',
        'class_id',
        'academic_year_id',
        'enrollment_type',
        'enrolled_at',
        'notes',
        'enrolled_by',
    ];

    protected function casts(): array
    {
        return ['enrolled_at' => 'date'];
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'class_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
