<?php

namespace Modules\Timetable\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Timetable\Entities\TimetableNotification;
use Modules\Timetable\Entities\TimetableNotificationSetting;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\Timetable\Notifications\TimetableReminderNotification;

/**
 * Story 13 - Notifications Rappels
 * Job planifié pour envoyer les rappels d'emploi du temps
 * À exécuter via le scheduler Laravel (daily)
 */
class SendTimetableRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $semesterId = null,
        public string $period = 'tomorrow'
    ) {}

    public function handle(): void
    {
        $targetDate = $this->getTargetDate();
        $dayOfWeek = $this->getDayOfWeekName($targetDate);

        // Get all slots for target day
        $query = TimetableSlot::with(['module', 'teacher', 'room', 'group'])
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true);

        if ($this->semesterId) {
            $query->where('semester_id', $this->semesterId);
        }

        $slots = $query->get();

        if ($slots->isEmpty()) {
            return;
        }

        // Group slots by students/teachers
        $userSlots = $this->groupSlotsByUsers($slots);

        foreach ($userSlots as $userId => $userSlotCollection) {
            $this->sendReminderToUser($userId, $userSlotCollection, $targetDate);
        }
    }

    private function getTargetDate(): Carbon
    {
        return match ($this->period) {
            'today' => now(),
            'tomorrow' => now()->addDay(),
            '2days' => now()->addDays(2),
            default => now()->addDay(),
        };
    }

    private function getDayOfWeekName(Carbon $date): string
    {
        $days = [
            0 => 'Dimanche',
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
        ];

        return $days[$date->dayOfWeek];
    }

    private function groupSlotsByUsers(Collection $slots): array
    {
        $userSlots = [];

        foreach ($slots as $slot) {
            // Add for teacher
            if ($slot->teacher_id) {
                $userSlots[$slot->teacher_id][] = $slot;
            }

            // Add for students in group
            if ($slot->group && method_exists($slot->group, 'students')) {
                $students = $slot->group->students()->with('user')->get();
                foreach ($students as $student) {
                    if ($student->user_id) {
                        $userSlots[$student->user_id][] = $slot;
                    }
                }
            }
        }

        return $userSlots;
    }

    private function sendReminderToUser(int $userId, array $slots, Carbon $targetDate): void
    {
        // Check user settings
        $settings = TimetableNotificationSetting::where('user_id', $userId)->first();

        if ($settings && ! $settings->isTypeEnabled('reminder')) {
            return;
        }

        // Check reminder timing preference
        if ($settings) {
            $preferredTiming = $settings->reminder_timing;
            $hoursUntilTarget = now()->diffInHours($targetDate, false);

            $requiredHours = match ($preferredTiming) {
                '1h' => 1,
                '2h' => 2,
                '24h' => 24,
                '48h' => 48,
                default => 24,
            };

            // Skip if not the right time for this user's preference
            if (abs($hoursUntilTarget - $requiredHours) > 2) {
                return;
            }
        }

        $slotsCollection = collect($slots)->sortBy('start_time');

        // Check if reminder already sent today for this date
        $alreadySent = TimetableNotification::where('user_id', $userId)
            ->where('type', 'reminder')
            ->whereDate('created_at', now()->toDateString())
            ->whereJsonContains('data->target_date', $targetDate->toDateString())
            ->exists();

        if ($alreadySent) {
            return;
        }

        // Get user
        $user = DB::connection('tenant')
            ->table('users')
            ->find($userId);

        if (! $user) {
            return;
        }

        // Create notification record
        TimetableNotification::create([
            'user_id' => $userId,
            'type' => 'reminder',
            'title' => "Rappel: {$slotsCollection->count()} séance(s) ".$this->getPeriodLabel(),
            'message' => $this->buildMessage($slotsCollection),
            'data' => [
                'period' => $this->period,
                'target_date' => $targetDate->toDateString(),
                'slots_count' => $slotsCollection->count(),
                'slots' => $slotsCollection->map(fn ($s) => [
                    'id' => $s->id,
                    'module' => $s->module?->name,
                    'time' => "{$s->start_time} - {$s->end_time}",
                    'room' => $s->room?->name,
                ])->toArray(),
            ],
            'sent_at' => now(),
        ]);

        // Create user object for notification
        $userModel = new \Modules\UsersGuard\Entities\User;
        $userModel->id = $user->id;
        $userModel->name = $user->name;
        $userModel->email = $user->email;
        $userModel->exists = true;

        // Send notification
        $notification = new TimetableReminderNotification($slotsCollection, $this->period);
        $userModel->notify($notification);
    }

    private function getPeriodLabel(): string
    {
        return match ($this->period) {
            'today' => "aujourd'hui",
            'tomorrow' => 'demain',
            '2days' => 'dans 2 jours',
            default => $this->period,
        };
    }

    private function buildMessage(Collection $slots): string
    {
        $count = $slots->count();
        $period = $this->getPeriodLabel();

        $firstSlot = $slots->first();
        $lastSlot = $slots->last();

        return "Vous avez {$count} séance(s) {$period} de {$firstSlot->start_time} à {$lastSlot->end_time}.";
    }
}
