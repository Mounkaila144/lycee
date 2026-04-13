<?php

namespace Modules\Finance\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSchedule extends Model
{
    protected $connection = 'tenant';

    protected $table = 'payment_schedules';

    protected $fillable = [
        'invoice_id',
        'installment_number',
        'due_date',
        'amount',
        'status',
        'paid_amount',
        'paid_date',
    ];

    protected function casts(): array
    {
        return [
            'installment_number' => 'integer',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'paid_date' => 'date',
        ];
    }

    /**
     * Invoice relationship
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get balance due
     */
    public function getBalanceAttribute(): float
    {
        return (float) ($this->amount - $this->paid_amount);
    }

    /**
     * Check if fully paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid' || $this->balance <= 0;
    }

    /**
     * Check if overdue
     */
    public function isOverdue(): bool
    {
        return $this->status === 'overdue' ||
               ($this->due_date < now() && $this->balance > 0);
    }

    /**
     * Scope for pending installments
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for overdue installments
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue')
            ->orWhere(function ($q) {
                $q->where('status', 'pending')
                    ->where('due_date', '<', now());
            });
    }

    /**
     * Scope for paid installments
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
