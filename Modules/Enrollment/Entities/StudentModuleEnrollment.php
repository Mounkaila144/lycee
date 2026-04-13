<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Database\Factories\StudentModuleEnrollmentFactory;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;

class StudentModuleEnrollment extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'student_module_enrollments';

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
        return StudentModuleEnrollmentFactory::new();
    }

    protected $fillable = [
        'student_id',
        'student_enrollment_id',
        'module_id',
        'semester_id',
        'enrollment_date',
        'status',
        'is_optional',
        'notes',
        'enrolled_by',
    ];

    /**
     * Valid statuses
     */
    public const VALID_STATUSES = ['Inscrit', 'Validé', 'Non validé', 'Abandonné', 'Dispensé'];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'enrollment_date' => 'date',
            'is_optional' => 'boolean',
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

    public function studentEnrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    /**
     * Accessors
     */
    public function getCreditsAttribute(): int
    {
        return $this->module->credits_ects ?? 0;
    }

    public function getModuleCodeAttribute(): ?string
    {
        return $this->module->code ?? null;
    }

    public function getModuleNameAttribute(): ?string
    {
        return $this->module->name ?? null;
    }

    /**
     * Scopes
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeInscrit(Builder $query): Builder
    {
        return $query->where('status', 'Inscrit');
    }

    public function scopeValide(Builder $query): Builder
    {
        return $query->where('status', 'Validé');
    }

    public function scopeNonValide(Builder $query): Builder
    {
        return $query->where('status', 'Non validé');
    }

    public function scopeOptional(Builder $query): Builder
    {
        return $query->where('is_optional', true);
    }

    public function scopeObligatoire(Builder $query): Builder
    {
        return $query->where('is_optional', false);
    }

    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForModule(Builder $query, ?int $moduleId): Builder
    {
        if ($moduleId === null) {
            return $query;
        }

        return $query->where('module_id', $moduleId);
    }

    public function scopeBySemester(Builder $query, int $semesterId): Builder
    {
        return $query->where('semester_id', $semesterId);
    }

    /**
     * Business methods
     */
    public function isEnrolled(): bool
    {
        return $this->status === 'Inscrit';
    }

    public function isValidated(): bool
    {
        return $this->status === 'Validé';
    }

    public function hasGrades(): bool
    {
        // TODO: Check if grades exist for this module enrollment
        // For now, return false
        return false;
    }

    public function canBeRemoved(): bool
    {
        // Cannot remove if grades have been entered or module is validated
        return ! $this->hasGrades() && ! $this->isValidated();
    }

    public function canBeAbandoned(): bool
    {
        return $this->isEnrolled();
    }

    /**
     * Check if enrollment to this module is allowed
     * (e.g., prerequisites validated)
     */
    public function checkPrerequisites(): array
    {
        $missingPrerequisites = [];

        $prerequisites = $this->module->prerequisites;

        if ($prerequisites->isEmpty()) {
            return [];
        }

        foreach ($prerequisites as $prerequisite) {
            // Check if student has validated this prerequisite
            $validated = static::where('student_id', $this->student_id)
                ->where('module_id', $prerequisite->id)
                ->where('status', 'Validé')
                ->exists();

            if (! $validated) {
                $missingPrerequisites[] = [
                    'id' => $prerequisite->id,
                    'code' => $prerequisite->code,
                    'name' => $prerequisite->name,
                ];
            }
        }

        return $missingPrerequisites;
    }
}
