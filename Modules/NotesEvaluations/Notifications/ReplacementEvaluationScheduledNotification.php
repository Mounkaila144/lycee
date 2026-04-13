<?php

namespace Modules\NotesEvaluations\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\NotesEvaluations\Entities\ReplacementEvaluation;

class ReplacementEvaluationScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ReplacementEvaluation $replacement
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $evaluation = $this->replacement->originalEvaluation;

        return (new MailMessage)
            ->subject('Convocation - Évaluation de remplacement')
            ->greeting("Bonjour {$notifiable->firstname},")
            ->line('Vous êtes convoqué(e) à une évaluation de remplacement.')
            ->line("Module: {$evaluation->module->name}")
            ->line("Évaluation: {$evaluation->name}")
            ->line("Date et heure: {$this->replacement->scheduled_at->format('d/m/Y H:i')}")
            ->when($this->replacement->location, fn ($mail) => $mail->line("Lieu: {$this->replacement->location}"))
            ->line('Type: '.($this->replacement->type === 'same' ? 'Même évaluation' : 'Évaluation alternative'))
            ->line('Veuillez vous présenter à l\'heure indiquée muni(e) de votre carte d\'étudiant.')
            ->action('Voir mes évaluations', url('/student/evaluations'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'replacement_evaluation_scheduled',
            'replacement_id' => $this->replacement->id,
            'evaluation_id' => $this->replacement->original_evaluation_id,
            'module_name' => $this->replacement->originalEvaluation->module->name ?? 'Module',
            'scheduled_at' => $this->replacement->scheduled_at->toIso8601String(),
            'location' => $this->replacement->location,
        ];
    }
}
