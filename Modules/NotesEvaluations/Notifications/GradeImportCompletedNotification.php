<?php

namespace Modules\NotesEvaluations\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;

class GradeImportCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $report,
        public ModuleEvaluationConfig $evaluation
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->report['failed'] ?? false
            ? 'Échec de l\'import des notes'
            : 'Import des notes terminé';

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Bonjour {$notifiable->name},");

        if ($this->report['failed'] ?? false) {
            $mail->line('L\'import des notes a échoué.')
                ->line("Module: {$this->evaluation->module->name}")
                ->line("Évaluation: {$this->evaluation->name}")
                ->error();
        } else {
            $mail->line('L\'import des notes est terminé.')
                ->line("Module: {$this->evaluation->module->name}")
                ->line("Évaluation: {$this->evaluation->name}")
                ->line("Notes importées: {$this->report['imported']}")
                ->line("Notes mises à jour: {$this->report['updated']}")
                ->line("Notes ignorées: {$this->report['skipped']}");

            if (count($this->report['errors']) > 0) {
                $mail->line('Erreurs: '.count($this->report['errors']));
            }
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'grade_import_completed',
            'evaluation_id' => $this->evaluation->id,
            'evaluation_name' => $this->evaluation->name,
            'module_name' => $this->evaluation->module->name ?? 'Module',
            'imported' => $this->report['imported'] ?? 0,
            'updated' => $this->report['updated'] ?? 0,
            'skipped' => $this->report['skipped'] ?? 0,
            'errors_count' => count($this->report['errors'] ?? []),
            'failed' => $this->report['failed'] ?? false,
        ];
    }
}
