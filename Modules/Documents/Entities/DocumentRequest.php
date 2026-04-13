<?php

namespace Modules\Documents\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Documents\Database\Factories\DocumentRequestFactory;
use Modules\Enrollment\Entities\Student;
use Modules\UsersGuard\Entities\User;

class DocumentRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): DocumentRequestFactory
    {
        return DocumentRequestFactory::new();
    }

    protected $connection = 'tenant';

    protected $table = 'document_requests';

    protected $fillable = [
        'student_id',
        'document_type',
        'quantity',
        'reason',
        'urgency',
        'request_date',
        'expected_delivery_date',
        'status',
        'processed_by',
        'processed_at',
        'processing_notes',
        'rejection_reason',
        'generated_document_id',
        'fee_amount',
        'fee_paid',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'expected_delivery_date' => 'date',
            'processed_at' => 'datetime',
            'fee_amount' => 'decimal:2',
            'fee_paid' => 'boolean',
        ];
    }

    /**
     * Relations
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function generatedDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'generated_document_id');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeUrgent($query)
    {
        return $query->where('urgency', 'urgent');
    }

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * Business Logic
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isUrgent(): bool
    {
        return $this->urgency === 'urgent';
    }

    public function approve(int $processedBy, ?string $notes = null): bool
    {
        return $this->update([
            'status' => 'approved',
            'processed_by' => $processedBy,
            'processed_at' => now(),
            'processing_notes' => $notes,
        ]);
    }

    public function reject(int $processedBy, string $reason): bool
    {
        return $this->update([
            'status' => 'rejected',
            'processed_by' => $processedBy,
            'processed_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function markAsProcessing(): bool
    {
        return $this->update(['status' => 'processing']);
    }

    public function markAsCompleted(int $documentId): bool
    {
        return $this->update([
            'status' => 'completed',
            'generated_document_id' => $documentId,
        ]);
    }

    public function markAsDelivered(): bool
    {
        return $this->update(['status' => 'delivered']);
    }

    public function isOverdue(): bool
    {
        if (! $this->expected_delivery_date) {
            return false;
        }

        return now()->isAfter($this->expected_delivery_date) && ! $this->isCompleted();
    }

    public function calculateExpectedDeliveryDate(): \Carbon\Carbon
    {
        $days = $this->isUrgent() ? 2 : 5;

        return $this->request_date->addBusinessDays($days);
    }
}
