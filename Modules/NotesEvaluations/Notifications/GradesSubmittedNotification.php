<?php

namespace Modules\NotesEvaluations\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\NotesEvaluations\Entities\GradeValidation;

class GradesSubmittedNotification extends Notification implements ShouldQueue
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
        $evaluation = $this->validation->evaluation;
        $module = $this->validation->module;
        $teacher = $this->validation->submitter;

        return (new MailMessage)
            ->subject('Nouvelle demande de validation des notes')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Une nouvelle demande de validation des notes a été soumise.')
            ->line("Module: {$module->name}")
            ->when($evaluation, fn ($mail) => $mail->line("Évaluation: {$evaluation->name}"))
            ->line("Enseignant: {$teacher->name}")
            ->line("Soumis le: {$this->validation->submitted_at->format('d/m/Y H:i')}")
            ->when($this->validation->hasAnomalies(), function ($mail) {
                return $mail->line('⚠️ Des anomalies ont été détectées.')
                    ->line(implode(', ', $this->validation->anomalies ?? []));
            })
            ->action('Voir les détails', url('/admin/grade-validations/'.$this->validation->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'grades_submitted',
            'validation_id' => $this->validation->id,
            'module_name' => $this->validation->module->name,
            'evaluation_name' => $this->validation->evaluation?->name,
            'teacher_name' => $this->validation->submitter->name,
            'submitted_at' => $this->validation->submitted_at->toIso8601String(),
            'has_anomalies' => $this->validation->hasAnomalies(),
        ];
    }
}
