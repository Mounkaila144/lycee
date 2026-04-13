<?php

namespace Modules\Finance\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Enrollment\Entities\Student;

class PaymentReminder extends Model
{
    protected $connection = 'tenant';

    protected $table = 'payment_reminders';

    protected $fillable = [
        'invoice_id',
        'student_id',
        'reminder_date',
        'reminder_type',
        'status',
        'sent_at',
        'send_methods',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reminder_date' => 'date',
            'sent_at' => 'datetime',
            'send_methods' => 'array',
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
     * Student relationship
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Check if reminder is sent
     */
    public function isSent(): bool
    {
        return $this->status === 'sent' && $this->sent_at !== null;
    }

    /**
     * Scope for pending reminders
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for sent reminders
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope for reminders due today
     */
    public function scopeDueToday($query)
    {
        return $query->where('reminder_date', '<=', today())
            ->where('status', 'pending');
    }

    /**
     * Scope by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('reminder_type', $type);
    }
}
