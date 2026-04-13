<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\UsersGuard\Entities\User;

class RetakeGrade extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'retake_grades';

    protected $fillable = [
        'retake_enrollment_id',
        'score',
        'is_absent',
        'entered_by',
        'entered_at',
        'status',
        'submitted_at',
        'validated_at',
        'published_at',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'is_absent' => 'boolean',
            'entered_at' => 'datetime',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
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

    public function retakeEnrollment(): BelongsTo
    {
        return $this->belongsTo(RetakeEnrollment::class);
    }

    public function enteredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    // Scopes

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeValidated($query)
    {
        return $query->where('status', 'validated');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeGraded($query)
    {
        return $query->whereNotNull('score')->orWhere('is_absent', true);
    }

    // Computed Attributes

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Brouillon',
            'submitted' => 'Soumis',
            'validated' => 'Validé',
            'published' => 'Publié',
            default => 'Inconnu',
        };
    }

    public function getEffectiveScoreAttribute(): ?float
    {
        if ($this->is_absent) {
            return null;
        }

        return $this->score;
    }

    public function getNewAverageAttribute(): ?float
    {
        $enrollment = $this->retakeEnrollment;

        if (! $enrollment) {
            return null;
        }

        if ($this->is_absent || $this->score === null) {
            return $enrollment->original_average;
        }

        return max($enrollment->original_average ?? 0, $this->score);
    }

    public function getIsImprovedAttribute(): bool
    {
        $enrollment = $this->retakeEnrollment;

        if (! $enrollment || $this->is_absent || $this->score === null) {
            return false;
        }

        return $this->score > ($enrollment->original_average ?? 0);
    }

    public function getImprovementAmountAttribute(): ?float
    {
        if (! $this->is_improved) {
            return null;
        }

        $enrollment = $this->retakeEnrollment;

        return $this->score - ($enrollment->original_average ?? 0);
    }

    // Business Logic

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isValidated(): bool
    {
        return $this->status === 'validated';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function canBeModified(): bool
    {
        return $this->status === 'draft';
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === 'draft' && ($this->score !== null || $this->is_absent);
    }

    public function canBeValidated(): bool
    {
        return $this->status === 'submitted';
    }

    public function canBePublished(): bool
    {
        return $this->status === 'validated';
    }

    public function submit(): void
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function validate(): void
    {
        $this->update([
            'status' => 'validated',
            'validated_at' => now(),
        ]);
    }

    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Update retake enrollment status
        $this->retakeEnrollment?->markAsGraded();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Modules\NotesEvaluations\Database\Factories\RetakeGradeFactory::new();
    }
}
