<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Entities\Student;
use Modules\UsersGuard\Entities\User;

class JuryDecision extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'jury_decisions';

    protected $fillable = [
        'deliberation_session_id',
        'student_id',
        'semester_result_id',
        'decision',
        'average_at_decision',
        'acquired_credits_at_decision',
        'missing_credits_at_decision',
        'justification',
        'conditions',
        'is_exceptional',
        'exceptional_reason',
        'votes_for',
        'votes_against',
        'abstentions',
        'decided_by',
        'decided_at',
        'requires_review',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'average_at_decision' => 'decimal:2',
            'acquired_credits_at_decision' => 'integer',
            'missing_credits_at_decision' => 'integer',
            'conditions' => 'array',
            'is_exceptional' => 'boolean',
            'votes_for' => 'integer',
            'votes_against' => 'integer',
            'abstentions' => 'integer',
            'decided_at' => 'datetime',
            'requires_review' => 'boolean',
            'reviewed_at' => 'datetime',
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

    public function deliberationSession(): BelongsTo
    {
        return $this->belongsTo(DeliberationSession::class, 'deliberation_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function semesterResult(): BelongsTo
    {
        return $this->belongsTo(SemesterResult::class);
    }

    public function decidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Scopes

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeByDecision($query, string $decision)
    {
        return $query->where('decision', $decision);
    }

    public function scopeExceptional($query)
    {
        return $query->where('is_exceptional', true);
    }

    public function scopeRequiringReview($query)
    {
        return $query->where('requires_review', true)->whereNull('reviewed_at');
    }

    public function scopeReviewed($query)
    {
        return $query->whereNotNull('reviewed_at');
    }

    // Computed Attributes

    public function getDecisionLabelAttribute(): string
    {
        return match ($this->decision) {
            'validated' => 'Validé',
            'compensated' => 'Admis par compensation',
            'retake' => 'Rattrapage',
            'repeat_year' => 'Redoublement',
            'exclusion' => 'Exclusion',
            'conditional' => 'Admission conditionnelle',
            'deferred' => 'Ajourné',
            default => 'Non défini',
        };
    }

    public function getDecisionColorAttribute(): string
    {
        return match ($this->decision) {
            'validated' => 'green',
            'compensated' => 'blue',
            'retake' => 'orange',
            'repeat_year' => 'red',
            'exclusion' => 'gray',
            'conditional' => 'yellow',
            'deferred' => 'gray',
            default => 'gray',
        };
    }

    public function getIsPositiveDecisionAttribute(): bool
    {
        return in_array($this->decision, ['validated', 'compensated', 'conditional']);
    }

    public function getIsNegativeDecisionAttribute(): bool
    {
        return in_array($this->decision, ['repeat_year', 'exclusion']);
    }

    public function getVoteSummaryAttribute(): ?string
    {
        if ($this->votes_for === null) {
            return null;
        }

        return sprintf(
            '%d pour, %d contre, %d abstentions',
            $this->votes_for,
            $this->votes_against ?? 0,
            $this->abstentions ?? 0
        );
    }

    public function getIsReviewedAttribute(): bool
    {
        return $this->reviewed_at !== null;
    }

    // Business Logic

    public function markAsReviewed(int $reviewerId): void
    {
        $this->update([
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'requires_review' => false,
        ]);
    }

    public function applyToSemesterResult(): void
    {
        $result = $this->semesterResult;

        if (! $result) {
            return;
        }

        $updates = [];

        // Update semester result based on decision
        switch ($this->decision) {
            case 'validated':
            case 'compensated':
                $updates['is_validated'] = true;
                $updates['global_status'] = $this->decision === 'compensated' ? 'partially_validated' : 'validated';
                $updates['can_progress_next_year'] = true;
                break;

            case 'conditional':
                $updates['is_validated'] = true;
                $updates['global_status'] = 'partially_validated';
                $updates['can_progress_next_year'] = true;
                break;

            case 'retake':
                $updates['global_status'] = 'to_retake';
                $updates['can_progress_next_year'] = false;
                break;

            case 'repeat_year':
            case 'exclusion':
                $updates['is_validated'] = false;
                $updates['global_status'] = 'deferred';
                $updates['can_progress_next_year'] = false;
                break;

            case 'deferred':
                $updates['global_status'] = 'deferred';
                break;
        }

        if (! empty($updates)) {
            $result->update($updates);
        }
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Modules\NotesEvaluations\Database\Factories\JuryDecisionFactory::new();
    }
}
