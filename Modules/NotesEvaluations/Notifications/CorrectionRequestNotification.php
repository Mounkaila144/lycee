<?php

namespace Modules\NotesEvaluations\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\NotesEvaluations\Entities\GradeCorrectionRequest;

class CorrectionRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public GradeCorrectionRequest $request,
        public string $action
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $grade = $this->request->grade;
        $student = $grade->student;
        $evaluation = $grade->evaluation;

        $mail = (new MailMessage)
            ->greeting("Bonjour {$notifiable->name},");

        switch ($this->action) {
            case 'new':
                $mail->subject('Nouvelle demande de correction de note')
                    ->line('Une nouvelle demande de correction de note a été soumise.')
                    ->line("Étudiant: {$student->full_name}")
                    ->line("Module: {$evaluation->module->name}")
                    ->line("Évaluation: {$evaluation->name}")
                    ->line("Changement proposé: {$this->request->getFormattedChange()}")
                    ->line("Motif: {$this->request->reason}")
                    ->action('Traiter la demande', url('/admin/correction-requests/'.$this->request->id));
                break;

            case 'approved':
                $mail->subject('Demande de correction approuvée')
                    ->line('Votre demande de correction de note a été approuvée.')
                    ->line("Étudiant: {$student->full_name}")
                    ->line("Module: {$evaluation->module->name}")
                    ->line('Vous disposez de 24 heures pour effectuer la modification.')
                    ->action('Modifier la note', url('/teacher/grades'));
                break;

            case 'rejected':
                $mail->subject('Demande de correction rejetée')
                    ->line('Votre demande de correction de note a été rejetée.')
                    ->line("Étudiant: {$student->full_name}")
                    ->line("Module: {$evaluation->module->name}")
                    ->line("Commentaire: {$this->request->review_comment}")
                    ->error();
                break;
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'correction_request',
            'action' => $this->action,
            'request_id' => $this->request->id,
            'grade_id' => $this->request->grade_id,
            'student_name' => $this->request->grade->student->full_name ?? 'Inconnu',
            'module_name' => $this->request->grade->evaluation->module->name ?? 'Inconnu',
            'change' => $this->request->getFormattedChange(),
        ];
    }
}
