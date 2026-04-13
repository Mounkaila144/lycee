<?php

namespace Modules\Timetable\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Timetable\Services\TimetableNotificationService;

/**
 * Controller pour les notifications d'emploi du temps
 * Epic 3 - Stories 10-13
 */
class TimetableNotificationController extends Controller
{
    public function __construct(
        private TimetableNotificationService $notificationService
    ) {}

    /**
     * Get current user's notifications
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|in:change,cancellation,replacement,reminder',
            'unread_only' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $notifications = $this->notificationService->getUserNotifications(
            userId: auth()->id(),
            type: $validated['type'] ?? null,
            unreadOnly: $validated['unread_only'] ?? false,
            perPage: $validated['per_page'] ?? 15
        );

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $this->notificationService->getUnreadCount(auth()->id()),
        ]);
    }

    /**
     * Get unread count
     */
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->notificationService->getUnreadCount(auth()->id()),
        ]);
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(int $notificationId): JsonResponse
    {
        $success = $this->notificationService->markAsRead($notificationId, auth()->id());

        if (! $success) {
            return response()->json(['message' => 'Notification non trouvée'], 404);
        }

        return response()->json([
            'message' => 'Notification marquée comme lue',
            'unread_count' => $this->notificationService->getUnreadCount(auth()->id()),
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead(auth()->id());

        return response()->json([
            'message' => "{$count} notification(s) marquée(s) comme lue(s)",
            'unread_count' => 0,
        ]);
    }

    /**
     * Get notification settings
     */
    public function getSettings(): JsonResponse
    {
        $settings = $this->notificationService->getSettings(auth()->id());

        return response()->json(['settings' => $settings]);
    }

    /**
     * Update notification settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notify_changes' => 'nullable|boolean',
            'notify_cancellations' => 'nullable|boolean',
            'notify_replacements' => 'nullable|boolean',
            'notify_reminders' => 'nullable|boolean',
            'reminder_timing' => 'nullable|in:1h,2h,24h,48h',
            'channels' => 'nullable|array',
            'channels.*' => 'in:database,mail,sms',
            'quiet_hours_enabled' => 'nullable|boolean',
            'quiet_hours_start' => 'nullable|date_format:H:i',
            'quiet_hours_end' => 'nullable|date_format:H:i',
        ]);

        $settings = $this->notificationService->updateSettings(auth()->id(), $validated);

        return response()->json([
            'message' => 'Paramètres mis à jour',
            'settings' => $settings,
        ]);
    }

    /**
     * Get upcoming changes affecting the user
     */
    public function upcomingChanges(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:30',
        ]);

        $changes = $this->notificationService->getUpcomingChangesForUser(
            auth()->id(),
            $validated['days'] ?? 7
        );

        return response()->json([
            'upcoming_changes' => $changes,
            'total' => $changes->count(),
        ]);
    }

    /**
     * Manually trigger reminders (admin only)
     */
    public function triggerReminders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:tenant.semesters,id',
            'period' => 'nullable|in:today,tomorrow,2days',
        ]);

        $this->notificationService->sendReminders(
            $validated['semester_id'] ?? null,
            $validated['period'] ?? 'tomorrow'
        );

        return response()->json([
            'message' => 'Rappels programmés',
            'period' => $validated['period'] ?? 'tomorrow',
        ]);
    }

    /**
     * Get notification statistics (admin)
     */
    public function statistics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:tenant.semesters,id',
            'days' => 'nullable|integer|min:1|max:90',
        ]);

        $stats = $this->notificationService->getStatistics(
            $validated['semester_id'] ?? null,
            $validated['days'] ?? 30
        );

        return response()->json(['statistics' => $stats]);
    }

    /**
     * Cleanup old notifications (admin)
     */
    public function cleanup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days_old' => 'nullable|integer|min:7|max:365',
        ]);

        $deleted = $this->notificationService->cleanupOldNotifications(
            $validated['days_old'] ?? 30
        );

        return response()->json([
            'message' => "{$deleted} notification(s) anciennes supprimée(s)",
            'deleted_count' => $deleted,
        ]);
    }
}
