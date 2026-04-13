<?php

namespace Modules\Timetable\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UsersGuard\Entities\User;

class TimetableNotification extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'timetable_slot_id',
        'exception_id',
        'read_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    // ========== Relations ==========

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function timetableSlot(): BelongsTo
    {
        return $this->belongsTo(TimetableSlot::class);
    }

    public function exception(): BelongsTo
    {
        return $this->belongsTo(TimetableException::class, 'exception_id');
    }

    // ========== Scopes ==========

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ========== Methods ==========

    public function markAsRead(): void
    {
        if (! $this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    public function markAsSent(): void
    {
        if (! $this->sent_at) {
            $this->update(['sent_at' => now()]);
        }
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Get notification icon based on type
     */
    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'change' => 'edit',
            'cancellation' => 'x-circle',
            'replacement' => 'user-switch',
            'reminder' => 'bell',
            default => 'info',
        };
    }

    /**
     * Get notification color based on type
     */
    public function getColorAttribute(): string
    {
        return match ($this->type) {
            'change' => 'blue',
            'cancellation' => 'red',
            'replacement' => 'orange',
            'reminder' => 'green',
            default => 'gray',
        };
    }
}
