<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Enrollment\Database\Factories\StudentStatusHistoryFactory;
use Modules\UsersGuard\Entities\User;

class StudentStatusHistory extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'student_status_histories';

    protected $fillable = [
        'student_id',
        'old_status',
        'new_status',
        'reason',
        'effective_date',
        'changed_by',
        'document_path',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): StudentStatusHistoryFactory
    {
        return StudentStatusHistoryFactory::new();
    }

    /**
     * Get the student that owns the status history.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the user who changed the status.
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Scope to get history for a specific student.
     */
    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * Scope to get history within a date range.
     */
    public function scopeBetweenDates($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('effective_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get history for a specific status transition.
     */
    public function scopeTransitionTo($query, string $status)
    {
        return $query->where('new_status', $status);
    }

    /**
     * Check if the transition was a suspension.
     */
    public function isSuspension(): bool
    {
        return $this->new_status === 'Suspendu';
    }

    /**
     * Check if the transition was an exclusion.
     */
    public function isExclusion(): bool
    {
        return $this->new_status === 'Exclu';
    }

    /**
     * Check if the transition was a graduation.
     */
    public function isGraduation(): bool
    {
        return $this->new_status === 'Diplômé';
    }

    /**
     * Check if the transition was a reactivation.
     */
    public function isReactivation(): bool
    {
        return $this->old_status === 'Suspendu' && $this->new_status === 'Actif';
    }
}
