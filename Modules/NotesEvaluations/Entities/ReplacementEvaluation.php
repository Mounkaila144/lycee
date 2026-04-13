<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;

class ReplacementEvaluation extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'replacement_evaluations';

    protected $fillable = [
        'original_evaluation_id',
        'student_id',
        'scheduled_at',
        'location',
        'type',
        'convocation_sent_at',
        'grade_id',
        'comment',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'convocation_sent_at' => 'datetime',
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

    public function originalEvaluation(): BelongsTo
    {
        return $this->belongsTo(ModuleEvaluationConfig::class, 'original_evaluation_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    // Scopes

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeUpcoming($query)
    {
        return $query->scheduled()->where('scheduled_at', '>', now());
    }

    // Business Logic

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function hasGrade(): bool
    {
        return $this->grade_id !== null;
    }

    public function complete(Grade $grade): void
    {
        $this->update([
            'status' => 'completed',
            'grade_id' => $grade->id,
        ]);
    }

    public function cancel(?string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'comment' => $reason ?? $this->comment,
        ]);
    }

    public function markConvocationSent(): void
    {
        $this->update(['convocation_sent_at' => now()]);
    }
}
