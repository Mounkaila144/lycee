<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\NotesEvaluations\Services\AbsencePolicyService;

class GradeAbsence extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'grade_absences';

    protected $fillable = [
        'grade_id',
        'absence_type',
        'justification_id',
        'justification_deadline',
        'notification_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'justification_deadline' => 'datetime',
            'notification_sent_at' => 'datetime',
        ];
    }

    // Relations

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function justification(): BelongsTo
    {
        return $this->belongsTo(AbsenceJustification::class);
    }

    // Scopes

    public function scopeUnjustified($query)
    {
        return $query->where('absence_type', 'unjustified');
    }

    public function scopePending($query)
    {
        return $query->where('absence_type', 'pending');
    }

    public function scopeJustified($query)
    {
        return $query->where('absence_type', 'justified');
    }

    public function scopeNeedsReminder($query)
    {
        return $query->where('absence_type', 'pending')
            ->whereNull('notification_sent_at')
            ->where('justification_deadline', '>', now());
    }

    // Business Logic

    public function isUnjustified(): bool
    {
        return $this->absence_type === 'unjustified';
    }

    public function isPending(): bool
    {
        return $this->absence_type === 'pending';
    }

    public function isJustified(): bool
    {
        return $this->absence_type === 'justified';
    }

    public function isDeadlinePassed(): bool
    {
        return $this->justification_deadline->isPast();
    }

    /**
     * Check if absence should apply zero grade based on policy
     */
    public function getAppliesZeroGradeAttribute(): bool
    {
        $policyService = app(AbsencePolicyService::class);
        $policy = $policyService->getPolicy($this->grade->evaluation);

        return $this->absence_type === 'unjustified' && $policy->unjustified_grade_is_zero;
    }

    public function markAsJustified(): void
    {
        $this->update(['absence_type' => 'justified']);
    }

    public function markAsPending(): void
    {
        $this->update(['absence_type' => 'pending']);
    }
}
