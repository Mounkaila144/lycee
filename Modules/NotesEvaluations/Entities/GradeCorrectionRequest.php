<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\UsersGuard\Entities\User;

class GradeCorrectionRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'grade_correction_requests';

    protected $fillable = [
        'grade_id',
        'requested_by',
        'current_value',
        'proposed_value',
        'current_is_absent',
        'proposed_is_absent',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_comment',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'current_value' => 'decimal:2',
            'proposed_value' => 'decimal:2',
            'current_is_absent' => 'boolean',
            'proposed_is_absent' => 'boolean',
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
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

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'Approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'Rejected');
    }

    public function scopeActive($query)
    {
        return $query->approved()
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->approved()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    // Business Logic

    public function isPending(): bool
    {
        return $this->status === 'Pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'Approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'Rejected';
    }

    public function isExpired(): bool
    {
        return $this->isApproved()
            && $this->expires_at
            && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->isApproved() && ! $this->isExpired();
    }

    public function approve(Authenticatable $reviewer, ?string $comment = null): void
    {
        $this->update([
            'status' => 'Approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_comment' => $comment,
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function reject(Authenticatable $reviewer, string $comment): void
    {
        $this->update([
            'status' => 'Rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_comment' => $comment,
        ]);
    }

    public function getFormattedChange(): string
    {
        $currentDisplay = $this->current_is_absent ? 'ABS' : ($this->current_value ?? '-');
        $proposedDisplay = $this->proposed_is_absent ? 'ABS' : ($this->proposed_value ?? '-');

        return "{$currentDisplay} → {$proposedDisplay}";
    }
}
