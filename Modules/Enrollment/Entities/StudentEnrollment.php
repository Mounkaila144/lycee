<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Database\Factories\StudentEnrollmentFactory;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;

class StudentEnrollment extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'student_enrollments';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return StudentEnrollmentFactory::new();
    }

    protected $fillable = [
        'student_id',
        'programme_id',
        'academic_year_id',
        'semester_id',
        'level',
        'group_id',
        'enrollment_date',
        'status',
        'notes',
        'enrolled_by',
    ];

    /**
     * Valid levels
     */
    public const VALID_LEVELS = ['L1', 'L2', 'L3', 'M1', 'M2'];

    /**
     * Valid statuses
     */
    public const VALID_STATUSES = ['Actif', 'Suspendu', 'Annulé', 'Terminé', 'Validé', 'Rejeté'];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'enrollment_date' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
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
        return $this->hasMany(StudentModuleEnrollment::class);
    }

    /**
     * Accessors
     */
    public function getTotalCreditsAttribute(): int
    {
        return $this->moduleEnrollments()
            ->with('module')
            ->get()
            ->sum(fn ($enrollment) => $enrollment->module->credits_ects ?? 0);
    }

    public function getEnrolledModulesCountAttribute(): int
    {
        return $this->moduleEnrollments()->count();
    }

    /**
     * Scopes
     */
    public function scopeByLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'Actif');
    }

    public function scopeByProgramme(Builder $query, int $programmeId): Builder
    {
        return $query->where('programme_id', $programmeId);
    }

    public function scopeBySemester(Builder $query, int $semesterId): Builder
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeByAcademicYear(Builder $query, int $academicYearId): Builder
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * Business methods
     */
    public function isActive(): bool
    {
        return $this->status === 'Actif';
    }

    public function canBeModified(): bool
    {
        return $this->status === 'Actif';
    }

    public function canBeCancelled(): bool
    {
        // Cannot cancel if there are validated modules
        return ! $this->moduleEnrollments()
            ->whereIn('status', ['Validé', 'Non validé'])
            ->exists();
    }

    /**
     * Get modules available for enrollment based on programme, level, and semester
     */
    public static function getAvailableModules(int $programmeId, string $level, int $semesterId): \Illuminate\Support\Collection
    {
        return \Modules\StructureAcademique\Entities\Module::on('tenant')
            ->whereHas('programmes', function ($query) use ($programmeId) {
                $query->where('programmes.id', $programmeId);
            })
            ->where('level', $level)
            ->whereHas('semesters', function ($query) use ($semesterId) {
                $query->where('semesters.id', $semesterId);
            })
            ->get();
    }
}
