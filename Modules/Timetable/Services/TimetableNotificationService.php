<?php

namespace Modules\Timetable\Services;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Timetable\Entities\TimetableException;
use Modules\Timetable\Entities\TimetableNotification;
use Modules\Timetable\Entities\TimetableNotificationSetting;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\Timetable\Jobs\NotifyTimetableChangeJob;
use Modules\Timetable\Jobs\SendTimetableRemindersJob;

/**
 * Service pour gérer les notifications d'emploi du temps
 * Stories 10-13 (Epic 3)
 */
class TimetableNotificationService
{
    /**
     * Story 10 - Notifier un changement manuel
     */
    public function notifyChange(TimetableException $exception): void
    {
        NotifyTimetableChangeJob::dispatch($exception, 'change')
            ->onQueue('notifications');
    }

    /**
     * Story 11 - Notifier une annulation
     */
    public function notifyCancellation(TimetableException $exception): void
    {
        NotifyTimetableChangeJob::dispatch($exception, 'cancellation')
            ->onQueue('notifications');
    }

    /**
     * Story 12 - Notifier un remplacement d'enseignant
     */
    public function notifyTeacherReplacement(TimetableException $exception): void
    {
        NotifyTimetableChangeJob::dispatch($exception, 'replacement')
            ->onQueue('notifications');
    }

    /**
     * Story 13 - Envoyer les rappels pour une période
     */
    public function sendReminders(?int $semesterId = null, string $period = 'tomorrow'): void
    {
        SendTimetableRemindersJob::dispatch($semesterId, $period)
            ->onQueue('notifications');
    }

    /**
     * Get user notifications with pagination
     */
    public function getUserNotifications(
        int $userId,
        ?string $type = null,
        ?bool $unreadOnly = false,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = TimetableNotification::forUser($userId)
            ->with(['timetableSlot.module', 'exception'])
            ->orderByDesc('created_at');

        if ($type) {
            $query->ofType($type);
        }

        if ($unreadOnly) {
            $query->unread();
        }

        return $query->paginate($perPage);
    }

    /**
     * Get unread notification count for user
     */
    public function getUnreadCount(int $userId): int
    {
        return TimetableNotification::forUser($userId)->unread()->count();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = TimetableNotification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (! $notification) {
            return false;
        }

        $notification->markAsRead();

        return true;
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(int $userId): int
    {
        return TimetableNotification::forUser($userId)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * Delete old notifications (cleanup)
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        return TimetableNotification::where('created_at', '<', now()->subDays($daysOld))
            ->whereNotNull('read_at')
            ->delete();
    }

    /**
     * Get or create notification settings for user
     */
    public function getSettings(int $userId): TimetableNotificationSetting
    {
        return TimetableNotificationSetting::getOrCreate($userId);
    }

    /**
     * Update notification settings for user
     *
     * @param  array{
     *     notify_changes?: bool,
     *     notify_cancellations?: bool,
     *     notify_replacements?: bool,
     *     notify_reminders?: bool,
     *     reminder_timing?: string,
     *     channels?: array,
     *     quiet_hours_enabled?: bool,
     *     quiet_hours_start?: string,
     *     quiet_hours_end?: string
     * }  $data
     */
    public function updateSettings(int $userId, array $data): TimetableNotificationSetting
    {
        $settings = TimetableNotificationSetting::getOrCreate($userId);
        $settings->update($data);

        return $settings->fresh();
    }

    /**
     * Get upcoming changes/exceptions that user should know about
     */
    public function getUpcomingChangesForUser(int $userId, int $days = 7): Collection
    {
        // Get user's groups and teaching assignments
        $userSlotIds = $this->getUserSlotIds($userId);

        if ($userSlotIds->isEmpty()) {
            return collect();
        }

        return TimetableException::whereIn('timetable_slot_id', $userSlotIds)
            ->whereBetween('exception_date', [now(), now()->addDays($days)])
            ->with(['timetableSlot.module', 'timetableSlot.room'])
            ->orderBy('exception_date')
            ->get();
    }

    /**
     * Create manual exception and notify (used by controllers)
     */
    public function createExceptionWithNotification(
        int $slotId,
        Carbon $exceptionDate,
        string $type,
        array $originalValues,
        array $newValues,
        string $reason,
        bool $notify = true
    ): TimetableException {
        $exception = TimetableException::create([
            'timetable_slot_id' => $slotId,
            'exception_date' => $exceptionDate,
            'exception_type' => $type,
            'original_values' => $originalValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'notify_students' => $notify,
            'created_by' => auth()->id(),
        ]);

        // Notification will be triggered by observer if notify is true

        return $exception;
    }

    /**
     * Get slot IDs associated with a user (as student or teacher)
     */
    private function getUserSlotIds(int $userId): Collection
    {
        // Get slots where user is teacher
        $teacherSlots = TimetableSlot::where('teacher_id', $userId)
            ->pluck('id');

        // Get slots for groups the user belongs to (as student)
        // This requires the Enrollment module integration
        $studentSlots = collect();

        // Try to get student's group slots if enrollment module exists
        try {
            if (class_exists('Modules\Enrollment\Entities\PedagogicalEnrollment')) {
                $enrollment = \Modules\Enrollment\Entities\PedagogicalEnrollment::where('student_id', $userId)
                    ->where('status', 'validated')
                    ->first();

                if ($enrollment && $enrollment->group_id) {
                    $studentSlots = TimetableSlot::where('group_id', $enrollment->group_id)
                        ->pluck('id');
                }
            }
        } catch (\Throwable) {
            // Enrollment module not available
        }

        return $teacherSlots->merge($studentSlots)->unique();
    }

    /**
     * Get notification statistics for a period
     */
    public function getStatistics(?int $semesterId = null, int $days = 30): array
    {
        $query = TimetableNotification::where('created_at', '>=', now()->subDays($days));

        return [
            'total_sent' => (clone $query)->count(),
            'by_type' => [
                'change' => (clone $query)->ofType('change')->count(),
                'cancellation' => (clone $query)->ofType('cancellation')->count(),
                'replacement' => (clone $query)->ofType('replacement')->count(),
                'reminder' => (clone $query)->ofType('reminder')->count(),
            ],
            'read_rate' => $this->calculateReadRate($query),
            'period_days' => $days,
        ];
    }

    private function calculateReadRate($query): float
    {
        $total = (clone $query)->count();
        if ($total === 0) {
            return 0;
        }

        $read = (clone $query)->read()->count();

        return round(($read / $total) * 100, 2);
    }
}
