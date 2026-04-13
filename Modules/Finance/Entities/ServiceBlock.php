<?php

namespace Modules\Finance\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Enrollment\Entities\Student;
use Modules\UsersGuard\Entities\User;

class ServiceBlock extends Model
{
    protected $connection = 'tenant';

    protected $table = 'service_blocks';

    protected $fillable = [
        'student_id',
        'block_type',
        'reason',
        'blocked_at',
        'unblocked_at',
        'is_active',
        'blocked_by',
        'unblocked_by',
        'related_invoices',
    ];

    protected function casts(): array
    {
        return [
            'blocked_at' => 'datetime',
            'unblocked_at' => 'datetime',
            'is_active' => 'boolean',
            'related_invoices' => 'array',
        ];
    }

    /**
     * Student relationship
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * User who blocked the service
     */
    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    /**
     * User who unblocked the service
     */
    public function unblockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unblocked_by');
    }

    /**
     * Check if block is currently active
     */
    public function isActive(): bool
    {
        return $this->is_active && $this->unblocked_at === null;
    }

    /**
     * Scope for active blocks
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->whereNull('unblocked_at');
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('block_type', $type);
    }

    /**
     * Scope by student
     */
    public function scopeByStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * Check if student has active block of specific type
     */
    public static function hasActiveBlock(int $studentId, ?string $blockType = null): bool
    {
        $query = static::active()->byStudent($studentId);

        if ($blockType) {
            $query->where(function ($q) use ($blockType) {
                $q->where('block_type', $blockType)
                    ->orWhere('block_type', 'all');
            });
        }

        return $query->exists();
    }
}
