<?php

namespace Modules\Timetable\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Timetable\Entities\TimetableNotification;
use Modules\Timetable\Entities\TimetableNotificationSetting;

/**
 * Trait to add timetable notification relationships to User model
 * Add `use HasTimetableNotifications;` to your User model
 */
trait HasTimetableNotifications
{
    /**
     * Get the user's timetable notification settings
     */
    public function timetableNotificationSettings(): HasOne
    {
        return $this->hasOne(TimetableNotificationSetting::class);
    }

    /**
     * Get the user's timetable notifications
     */
    public function timetableNotifications(): HasMany
    {
        return $this->hasMany(TimetableNotification::class);
    }

    /**
     * Get unread timetable notifications count
     */
    public function unreadTimetableNotificationsCount(): int
    {
        return $this->timetableNotifications()->unread()->count();
    }
}
