<?php

namespace Modules\Timetable\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Modules\Timetable\Entities\TimetableException;
use Modules\Timetable\Entities\TimetableNotification;
use Modules\Timetable\Entities\TimetableNotificationSetting;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\Timetable\Notifications\SessionCancelledNotification;
use Modules\Timetable\Notifications\TeacherReplacedNotification;
use Modules\Timetable\Notifications\TimetableChangedNotification;

/**
 * Job pour notifier les changements d'emploi du temps
 * Gère les Stories 10, 11, 12
 */
class NotifyTimetableChangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public TimetableException $exception,
        public string $changeType = 'change'
    ) {}

    public function handle(): void
    {
        $exception = $this->exception->load(['timetableSlot.module', 'timetableSlot.teacher', 'timetableSlot.room', 'timetableSlot.group']);
        $slot = $exception->timetableSlot;

        if (! $slot) {
            return;
        }

        // Get affected users (students in the group + teacher)
        $users = $this->getAffectedUsers($slot);

        foreach ($users as $user) {
            $settings = TimetableNotificationSetting::getOrCreate($user->id);

            // Check if notification type is enabled
            if (! $settings->isTypeEnabled($this->changeType)) {
                continue;
            }

            // Create notification based on type
            $notification = $this->createNotification($slot, $exception, $user);

            if ($notification) {
                // Store in database
                TimetableNotification::create([
                    'user_id' => $user->id,
                    'type' => $this->changeType,
                    'title' => $this->getTitle($slot),
                    'message' => $this->getMessage($slot, $exception),
                    'data' => $notification->toArray($user),
                    'timetable_slot_id' => $slot->id,
                    'exception_id' => $exception->id,
                    'sent_at' => now(),
                ]);

                // Send Laravel notification
                $user->notify($notification);
            }
        }
    }

    private function getAffectedUsers(TimetableSlot $slot): Collection
    {
        $users = collect();

        // Add teacher
        if ($slot->teacher) {
            $users->push($slot->teacher);
        }

        // Add students from group
        if ($slot->group && method_exists($slot->group, 'students')) {
            $students = $slot->group->students()->with('user')->get();
            foreach ($students as $student) {
                if ($student->user) {
                    $users->push($student->user);
                }
            }
        }

        return $users->unique('id');
    }

    private function createNotification(TimetableSlot $slot, TimetableException $exception, $user)
    {
        return match ($this->changeType) {
            'cancellation' => new SessionCancelledNotification(
                $slot,
                $exception,
                $exception->new_values['rescheduled_to'] ?? null
            ),
            'replacement' => new TeacherReplacedNotification(
                $slot,
                $exception,
                $exception->original_values['teacher_name'] ?? $slot->teacher?->name ?? 'Inconnu',
                $exception->new_values['teacher_name'] ?? 'Nouvel enseignant'
            ),
            default => new TimetableChangedNotification(
                $slot,
                $exception,
                $this->getChanges($exception)
            ),
        };
    }

    private function getChanges(TimetableException $exception): array
    {
        $changes = [];
        $original = $exception->original_values ?? [];
        $new = $exception->new_values ?? [];

        foreach ($new as $key => $value) {
            if (isset($original[$key]) && $original[$key] !== $value) {
                $changes[$key] = "{$original[$key]} → {$value}";
            }
        }

        return $changes;
    }

    private function getTitle(TimetableSlot $slot): string
    {
        $module = $slot->module?->name ?? 'Séance';

        return match ($this->changeType) {
            'cancellation' => "Séance annulée: {$module}",
            'replacement' => "Changement d'enseignant: {$module}",
            default => "Modification: {$module}",
        };
    }

    private function getMessage(TimetableSlot $slot, TimetableException $exception): string
    {
        $date = $exception->exception_date->format('d/m/Y');
        $time = "{$slot->start_time} - {$slot->end_time}";

        return match ($this->changeType) {
            'cancellation' => "La séance du {$date} ({$time}) a été annulée.",
            'replacement' => "L'enseignant de la séance du {$date} ({$time}) a été remplacé.",
            default => "Des modifications ont été apportées à la séance du {$date} ({$time}).",
        };
    }
}
