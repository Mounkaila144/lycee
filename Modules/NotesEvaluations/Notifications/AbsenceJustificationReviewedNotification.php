<?php

namespace Modules\NotesEvaluations\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\NotesEvaluations\Entities\AbsenceJustification;

class AbsenceJustificationReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AbsenceJustification $justification,
        public string $decision
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $evaluation = $this->justification->evaluation;
        $isApproved = $this->decision === 'approved';

        $mail = (new MailMessage)
            ->greeting("Bonjour {$notifiable->firstname},");

        if ($isApproved) {
            $mail->subject('Justificatif d\'absence approuvé')
                ->line('Votre justificatif d\'absence a été approuvé.')
                ->line("Module: {$evaluation->module->name}")
                ->line("Évaluation: {$evaluation->name}")
                ->line('Vous pourrez être convoqué pour une évaluation de remplacement.');
        } else {
            $mail->subject('Justificatif d\'absence rejeté')
                ->line('Votre justificatif d\'absence a été rejeté.')
                ->line("Module: {$evaluation->module->name}")
                ->line("Évaluation: {$evaluation->name}")
                ->line("Commentaire: {$this->justification->admin_comment}")
                ->line('Votre absence sera comptabilisée selon la politique du module.')
                ->error();
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'absence_justification_reviewed',
            'justification_id' => $this->justification->id,
            'evaluation_id' => $this->justification->evaluation_id,
            'module_name' => $this->justification->evaluation->module->name ?? 'Module',
            'decision' => $this->decision,
            'comment' => $this->justification->admin_comment,
        ];
    }
}
