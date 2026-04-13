<?php

namespace Modules\Timetable\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UsersGuard\Entities\User;

class TimetableNotificationSetting extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'user_id',
        'notify_changes',
        'notify_cancellations',
        'notify_replacements',
        'notify_reminders',
        'reminder_timing',
        'channels',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
    ];

    protected function casts(): array
    {
        return [
            'notify_changes' => 'boolean',
            'notify_cancellations' => 'boolean',
            'notify_replacements' => 'boolean',
            'notify_reminders' => 'boolean',
            'channels' => 'array',
            'quiet_hours_enabled' => 'boolean',
            'quiet_hours_start' => 'datetime:H:i',
            'quiet_hours_end' => 'datetime:H:i',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a notification type is enabled
     */
    public function isTypeEnabled(string $type): bool
    {
        return match ($type) {
            'change' => $this->notify_changes,
            'cancellation' => $this->notify_cancellations,
            'replacement' => $this->notify_replacements,
            'reminder' => $this->notify_reminders,
            default => false,
        };
    }

    /**
     * Check if currently in quiet hours
     */
    public function isInQuietHours(): bool
    {
        if (! $this->quiet_hours_enabled || ! $this->quiet_hours_start || ! $this->quiet_hours_end) {
            return false;
        }

        $now = now()->format('H:i');
        $start = $this->quiet_hours_start->format('H:i');
        $end = $this->quiet_hours_end->format('H:i');

        if ($start <= $end) {
            return $now >= $start && $now <= $end;
        }

        // Quiet hours span midnight
        return $now >= $start || $now <= $end;
    }

    /**
     * Get or create settings for a user
     */
    public static function getOrCreate(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'notify_changes' => true,
                'notify_cancellations' => true,
                'notify_replacements' => true,
                'notify_reminders' => true,
                'reminder_timing' => '24h',
                'channels' => ['database', 'mail'],
            ]
        );
    }
}
