<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\UsersGuard\Entities\User;

class ClassEnrollment extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'class_enrollments';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'student_id',
        'classe_id',
        'academic_year_id',
        'previous_class_enrollment_id',
        'assigned_by',
        'enrolled_at',
        'assignment_type',
        'status',
        'final_decision',
        'final_decision_at',
        'notes',
    ];

    public const ASSIGNMENT_TYPES = [
        'Nouvelle inscription',
        'Passage',
        'Redoublement',
        'Transfert',
        'Réorientation',
    ];

    public const STATUSES = [
        'Actif',
        'Terminé',
        'Transféré',
        'Annulé',
    ];

    public const FINAL_DECISIONS = [
        'Passage',
        'Redoublement',
        'Exclusion',
        'Diplôme',
        'Abandon',
        'Transfert',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'date',
            'final_decision_at' => 'date',
            'deleted_at' => 'datetime',
        ];
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
        return $this->belongsTo(Classe::class, 'classe_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function previousClassEnrollment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_class_enrollment_id');
    }

    public function nextClassEnrollment(): HasOne
    {
        return $this->hasOne(self::class, 'previous_class_enrollment_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Actif');
    }

    public function scopeForAcademicYear($query, int $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function markCompleted(string $finalDecision): bool
    {
        if (! in_array($finalDecision, self::FINAL_DECISIONS, true)) {
            return false;
        }

        return $this->update([
            'status' => 'Terminé',
            'final_decision' => $finalDecision,
            'final_decision_at' => now()->toDateString(),
        ]);
    }

    public function isRepeat(): bool
    {
        return $this->assignment_type === 'Redoublement';
    }

    public function isPromotion(): bool
    {
        return $this->assignment_type === 'Passage';
    }
}
