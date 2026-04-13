<?php

namespace Modules\NotesEvaluations\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\NotesEvaluations\Entities\GradeValidation;

class GradesPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public GradeValidation $validation
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $module = $this->validation->module;
        $evaluation = $this->validation->evaluation;

        return (new MailMessage)
            ->subject('Nouvelles notes disponibles')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('De nouvelles notes ont été publiées.')
            ->line("Module: {$module->name}")
            ->when($evaluation, fn ($mail) => $mail->line("Évaluation: {$evaluation->name}"))
            ->line('Vous pouvez consulter vos notes dans votre espace étudiant.')
            ->action('Voir mes notes', url('/student/grades'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'grades_published',
            'validation_id' => $this->validation->id,
            'module_name' => $this->validation->module->name,
            'evaluation_name' => $this->validation->evaluation?->name,
            'published_at' => $this->validation->published_at?->toIso8601String(),
        ];
    }
}
