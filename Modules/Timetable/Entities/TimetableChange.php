<?php

namespace Modules\Timetable\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UsersGuard\Entities\User;

class TimetableChange extends Model
{
    protected $connection = 'tenant';

    protected $table = 'timetable_changes';

    protected $fillable = [
        'timetable_slot_id',
        'user_id',
        'action',
        'old_values',
        'new_values',
        'reason',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public const VALID_ACTIONS = ['Created', 'Updated', 'Deleted', 'Cancelled', 'Rescheduled'];

    /**
     * Relations
     */
    public function timetableSlot(): BelongsTo
    {
        return $this->belongsTo(TimetableSlot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeBySlot($query, int $slotId)
    {
        return $query->where('timetable_slot_id', $slotId);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Accessors
     */
    public function getChangeSummaryAttribute(): string
    {
        return match ($this->action) {
            'Created' => 'Séance créée',
            'Updated' => 'Séance modifiée',
            'Deleted' => 'Séance supprimée',
            'Cancelled' => 'Séance annulée',
            'Rescheduled' => 'Séance reprogrammée',
            default => 'Modification',
        };
    }
}
