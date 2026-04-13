<?php

namespace Modules\Timetable\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Timetable\Entities\TimetableException;
use Modules\Timetable\Entities\TimetableSlot;

/**
 * Story 10 - Alertes Modifications
 * Notification envoyée lors d'une modification d'emploi du temps
 */
class TimetableChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TimetableSlot $slot,
        public ?TimetableException $exception = null,
        public array $changes = []
    ) {}

    public function via(object $notifiable): array
    {
        $settings = $notifiable->timetableNotificationSettings;

        if ($settings && ! $settings->isTypeEnabled('change')) {
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
        $slot = $this->slot;
        $module = $slot->module?->name ?? 'Module inconnu';
        $date = $this->exception?->exception_date?->format('d/m/Y') ?? 'Date non spécifiée';

        return (new MailMessage)
            ->subject('Modification de votre emploi du temps')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Une modification a été apportée à votre emploi du temps.')
            ->line("**Module:** {$module}")
            ->line("**Date:** {$date}")
            ->line("**Jour:** {$slot->day_of_week}")
            ->line("**Horaire:** {$slot->start_time} - {$slot->end_time}")
            ->when(count($this->changes) > 0, function ($mail) {
                $changesList = collect($this->changes)->map(fn ($v, $k) => "- {$k}: {$v}")->implode("\n");
                $mail->line("**Modifications:**\n{$changesList}");
            })
            ->when($this->exception?->reason, fn ($mail) => $mail->line("**Raison:** {$this->exception->reason}"))
            ->action('Voir mon emploi du temps', url('/timetable'))
            ->line('Merci de votre compréhension.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'change',
            'slot_id' => $this->slot->id,
            'exception_id' => $this->exception?->id,
            'module_name' => $this->slot->module?->name,
            'day_of_week' => $this->slot->day_of_week,
            'start_time' => $this->slot->start_time,
            'end_time' => $this->slot->end_time,
            'changes' => $this->changes,
            'reason' => $this->exception?->reason,
            'exception_date' => $this->exception?->exception_date?->toDateString(),
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
