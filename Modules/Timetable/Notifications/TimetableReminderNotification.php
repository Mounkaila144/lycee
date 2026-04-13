<?php

namespace Modules\Timetable\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Story 13 - Notifications Rappels
 * Notification de rappel pour les séances à venir
 */
class TimetableReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Collection $upcomingSlots,
        public string $period = 'tomorrow'
    ) {}

    public function via(object $notifiable): array
    {
        $settings = $notifiable->timetableNotificationSettings;

        if ($settings && ! $settings->isTypeEnabled('reminder')) {
            return [];
        }

        $channels = $settings?->channels ?? ['database', 'mail'];

        if ($settings?->isInQuietHours()) {
            return array_filter($channels, fn ($c) => $c !== 'mail');
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $periodLabel = match ($this->period) {
            'today' => 'aujourd\'hui',
            'tomorrow' => 'demain',
            '2days' => 'dans 2 jours',
            default => $this->period,
        };

        $mail = (new MailMessage)
            ->subject("📅 Rappel: Vos cours {$periodLabel}")
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line("Voici vos séances prévues {$periodLabel}:");

        foreach ($this->upcomingSlots as $slot) {
            $module = $slot->module?->name ?? 'Module';
            $teacher = $slot->teacher?->name ?? 'Enseignant';
            $room = $slot->room?->name ?? 'Salle';
            $time = "{$slot->start_time} - {$slot->end_time}";

            $mail->line("• **{$time}** - {$module} ({$teacher}) - {$room}");
        }

        return $mail
            ->action('Voir mon emploi du temps', url('/timetable'))
            ->line('Bonne journée !');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'reminder',
            'period' => $this->period,
            'slots_count' => $this->upcomingSlots->count(),
            'slots' => $this->upcomingSlots->map(fn ($slot) => [
                'id' => $slot->id,
                'module_name' => $slot->module?->name,
                'teacher_name' => $slot->teacher?->name,
                'room_name' => $slot->room?->name,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
            ])->toArray(),
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
