<?php

namespace Modules\Finance\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Entities\Student;
use Modules\UsersGuard\Entities\User;

class Discount extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'discounts';

    protected $fillable = [
        'student_id',
        'fee_type_id',
        'type',
        'percentage',
        'amount',
        'reason',
        'valid_from',
        'valid_until',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'amount' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'approved_at' => 'datetime',
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
     * Fee type relationship
     */
    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    /**
     * User who approved the discount
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if discount is currently valid
     */
    public function isValid(): bool
    {
        $now = now();

        return $now->greaterThanOrEqualTo($this->valid_from) &&
               ($this->valid_until === null || $now->lessThanOrEqualTo($this->valid_until));
    }

    /**
     * Check if discount is approved
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Calculate discount amount for a given base amount
     */
    public function calculateDiscountAmount(float $baseAmount): float
    {
        if ($this->percentage) {
            return $baseAmount * ($this->percentage / 100);
        }

        return (float) $this->amount;
    }

    /**
     * Scope for valid discounts
     */
    public function scopeValid($query)
    {
        return $query->where('valid_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            });
    }

    /**
     * Scope for approved discounts
     */
    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_at');
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by student
     */
    public function scopeByStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }
}
