<?php

namespace Modules\Timetable\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Timetable\Entities\TimetableException;
use Modules\Timetable\Entities\TimetableSlot;

/**
 * Story 12 - Remplacements Enseignants
 * Notification envoyée lors du remplacement d'un enseignant
 */
class TeacherReplacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TimetableSlot $slot,
        public TimetableException $exception,
        public string $originalTeacherName,
        public string $newTeacherName
    ) {}

    public function via(object $notifiable): array
    {
        $settings = $notifiable->timetableNotificationSettings;

        if ($settings && ! $settings->isTypeEnabled('replacement')) {
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
        $date = $this->exception->exception_date->format('d/m/Y');

        return (new MailMessage)
            ->subject('Changement d\'enseignant - '.$module)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Un changement d\'enseignant a été effectué pour une de vos séances.')
            ->line("**Module:** {$module}")
            ->line("**Date:** {$date}")
            ->line("**Horaire:** {$slot->start_time} - {$slot->end_time}")
            ->line('**Salle:** '.($slot->room?->name ?? 'Non définie'))
            ->line('')
            ->line("**Enseignant initial:** {$this->originalTeacherName}")
            ->line("**Nouvel enseignant:** {$this->newTeacherName}")
            ->when($this->exception->reason, fn ($mail) => $mail->line("**Raison:** {$this->exception->reason}"))
            ->action('Voir mon emploi du temps', url('/timetable'))
            ->line('La séance aura bien lieu aux horaires prévus.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'replacement',
            'slot_id' => $this->slot->id,
            'exception_id' => $this->exception->id,
            'module_name' => $this->slot->module?->name,
            'room_name' => $this->slot->room?->name,
            'day_of_week' => $this->slot->day_of_week,
            'start_time' => $this->slot->start_time,
            'end_time' => $this->slot->end_time,
            'replacement_date' => $this->exception->exception_date->toDateString(),
            'original_teacher' => $this->originalTeacherName,
            'new_teacher' => $this->newTeacherName,
            'reason' => $this->exception->reason,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
