<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\UsersGuard\Entities\User;

class Grade extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'grades';

    protected $fillable = [
        'student_id',
        'evaluation_id',
        'score',
        'is_absent',
        'comment',
        'entered_by',
        'entered_at',
        'status',
        'is_visible_to_students',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'is_absent' => 'boolean',
            'is_visible_to_students' => 'boolean',
            'entered_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Resolve route binding for tenant connection
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    // Relations

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ModuleEvaluationConfig::class, 'evaluation_id');
    }

    public function enteredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(GradeHistory::class);
    }

    public function absence(): HasOne
    {
        return $this->hasOne(GradeAbsence::class);
    }

    public function correctionRequests(): HasMany
    {
        return $this->hasMany(GradeCorrectionRequest::class);
    }

    // Scopes

    public function scopeDraft($query)
    {
        return $query->where('status', 'Draft');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'Submitted');
    }

    public function scopeValidated($query)
    {
        return $query->where('status', 'Validated');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'Published');
    }

    public function scopeVisibleToStudents($query)
    {
        return $query->where('is_visible_to_students', true);
    }

    public function scopeForEvaluation($query, int $evaluationId)
    {
        return $query->where('evaluation_id', $evaluationId);
    }

    public function scopeNotAbsent($query)
    {
        return $query->where('is_absent', false);
    }

    public function scopeAbsent($query)
    {
        return $query->where('is_absent', true);
    }

    // Business Logic

    public function isPublished(): bool
    {
        return $this->status === 'Published';
    }

    public function canBeModified(): bool
    {
        return $this->status === 'Draft';
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === 'Draft';
    }

    public function requiresCorrectionRequest(): bool
    {
        return $this->status === 'Published';
    }

    public function markAsAbsent(bool $isAbsent = true): void
    {
        $this->update([
            'is_absent' => $isAbsent,
            'score' => $isAbsent ? null : $this->score,
        ]);
    }

    public function publish(): void
    {
        $this->update([
            'status' => 'Published',
            'is_visible_to_students' => true,
            'published_at' => now(),
        ]);
    }

    public function getEffectiveScore(): ?float
    {
        if ($this->is_absent) {
            // Check absence policy
            $absence = $this->absence;
            if ($absence && $absence->applies_zero_grade) {
                return 0;
            }

            return null;
        }

        return $this->score;
    }
}
