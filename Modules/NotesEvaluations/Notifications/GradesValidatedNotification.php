<?php

namespace Modules\NotesEvaluations\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\NotesEvaluations\Entities\GradeValidation;

class GradesValidatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public GradeValidation $validation,
        public string $decision
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $module = $this->validation->module;
        $evaluation = $this->validation->evaluation;
        $isApproved = $this->decision === 'Approved';

        $mail = (new MailMessage)
            ->subject($isApproved ? 'Notes validées' : 'Notes rejetées')
            ->greeting("Bonjour {$notifiable->name},");

        if ($isApproved) {
            $mail->line('Vos notes ont été validées.')
                ->line("Module: {$module->name}")
                ->when($evaluation, fn ($m) => $m->line("Évaluation: {$evaluation->name}"))
                ->line('Les notes peuvent maintenant être publiées.');
        } else {
            $mail->line('Votre demande de validation des notes a été rejetée.')
                ->line("Module: {$module->name}")
                ->when($evaluation, fn ($m) => $m->line("Évaluation: {$evaluation->name}"))
                ->line("Motif: {$this->validation->rejection_reason}")
                ->line('Veuillez corriger les notes et soumettre à nouveau.')
                ->error();
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'grades_validated',
            'validation_id' => $this->validation->id,
            'module_name' => $this->validation->module->name,
            'evaluation_name' => $this->validation->evaluation?->name,
            'decision' => $this->decision,
            'rejection_reason' => $this->validation->rejection_reason,
            'validated_at' => $this->validation->validated_at?->toIso8601String(),
        ];
    }
}
