<?php

namespace Modules\Timetable\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Timetable\Entities\TimetableException;
use Modules\Timetable\Entities\TimetableSlot;

/**
 * Story 11 - Annulations Séances
 * Notification envoyée lors de l'annulation d'une séance
 */
class SessionCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TimetableSlot $slot,
        public TimetableException $exception,
        public ?string $rescheduledTo = null
    ) {}

    public function via(object $notifiable): array
    {
        $settings = $notifiable->timetableNotificationSettings;

        if ($settings && ! $settings->isTypeEnabled('cancellation')) {
            return [];
        }

        $channels = $settings?->channels ?? ['database', 'mail'];

        // Always send cancellation by email (urgent)
        if (! in_array('mail', $channels)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $slot = $this->slot;
        $module = $slot->module?->name ?? 'Module inconnu';
        $date = $this->exception->exception_date->format('d/m/Y');
        $teacher = $slot->teacher?->name ?? 'Enseignant';

        $mail = (new MailMessage)
            ->subject('⚠️ Séance annulée - '.$module)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('**Une séance a été annulée.**')
            ->line("**Module:** {$module}")
            ->line("**Enseignant:** {$teacher}")
            ->line("**Date:** {$date}")
            ->line("**Horaire prévu:** {$slot->start_time} - {$slot->end_time}")
            ->line('**Salle:** '.($slot->room?->name ?? 'Non définie'));

        if ($this->exception->reason) {
            $mail->line("**Raison:** {$this->exception->reason}");
        }

        if ($this->rescheduledTo) {
            $mail->line("**Reportée au:** {$this->rescheduledTo}");
        }

        return $mail
            ->action('Voir mon emploi du temps', url('/timetable'))
            ->line('Nous vous prions de nous excuser pour ce désagrément.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'cancellation',
            'slot_id' => $this->slot->id,
            'exception_id' => $this->exception->id,
            'module_name' => $this->slot->module?->name,
            'teacher_name' => $this->slot->teacher?->name,
            'room_name' => $this->slot->room?->name,
            'day_of_week' => $this->slot->day_of_week,
            'start_time' => $this->slot->start_time,
            'end_time' => $this->slot->end_time,
            'cancelled_date' => $this->exception->exception_date->toDateString(),
            'reason' => $this->exception->reason,
            'rescheduled_to' => $this->rescheduledTo,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
