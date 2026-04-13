<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Database\Factories\PedagogicalEnrollmentFactory;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;

class PedagogicalEnrollment extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'student_enrollments';

    public const STATUS_ACTIVE = 'Actif';

    public const STATUS_SUSPENDED = 'Suspendu';

    public const STATUS_CANCELLED = 'Annulé';

    public const STATUS_COMPLETED = 'Terminé';

    public const STATUS_VALIDATED = 'Validé';

    public const STATUS_REJECTED = 'Rejeté';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
        self::STATUS_CANCELLED,
        self::STATUS_COMPLETED,
        self::STATUS_VALIDATED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'student_id',
        'programme_id',
        'level',
        'academic_year_id',
        'semester_id',
        'enrollment_date',
        'group_id',
        'status',
        'notes',
        'enrolled_by',
    ];

    protected function casts(): array
    {
        return [
            'enrollment_date' => 'date',
        ];
    }

    protected static function newFactory(): PedagogicalEnrollmentFactory
    {
        return PedagogicalEnrollmentFactory::new();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    public function moduleEnrollments(): HasMany
    {
        return $this->hasMany(StudentModuleEnrollment::class, 'student_enrollment_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeValidated($query)
    {
        return $query->where('status', self::STATUS_VALIDATED);
    }

    public function scopeForAcademicYear($query, int $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function canBeValidated(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function canBeRejected(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
