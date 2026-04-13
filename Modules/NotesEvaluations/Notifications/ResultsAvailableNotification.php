<?php

namespace Modules\NotesEvaluations\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\NotesEvaluations\Entities\PublicationRecord;
use Modules\NotesEvaluations\Entities\SemesterResult;

class ResultsAvailableNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SemesterResult $result,
        public PublicationRecord $publicationRecord
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $semester = $this->result->semester;
        $mention = $this->result->mention;
        $isValidated = $this->result->is_validated;

        $mail = (new MailMessage)
            ->subject('Résultats du semestre disponibles')
            ->greeting("Bonjour {$notifiable->firstname},");

        if ($isValidated) {
            $mail->line('Nous avons le plaisir de vous annoncer que vos résultats du semestre sont disponibles.')
                ->line("Semestre: {$semester->name}")
                ->line("Moyenne obtenue: {$this->result->average}/20")
                ->line("Mention: {$mention}")
                ->line("Crédits acquis: {$this->result->acquired_credits}/{$this->result->total_credits}");

            if ($this->result->rank) {
                $mail->line("Classement: {$this->result->rank_display}");
            }
        } else {
            $mail->line('Vos résultats du semestre sont maintenant disponibles.')
                ->line("Semestre: {$semester->name}")
                ->line("Moyenne obtenue: {$this->result->average}/20")
                ->line("Statut: {$this->result->global_status_label}")
                ->line("Crédits acquis: {$this->result->acquired_credits}/{$this->result->total_credits}");

            if ($this->result->failed_modules_count > 0) {
                $mail->line("Modules à rattraper: {$this->result->failed_modules_count}");
            }
        }

        return $mail
            ->line('Consultez votre espace étudiant pour plus de détails.')
            ->action('Voir mes résultats', url('/student/results'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'semester_results_available',
            'semester_id' => $this->result->semester_id,
            'semester_name' => $this->result->semester->name,
            'average' => $this->result->average,
            'is_validated' => $this->result->is_validated,
            'global_status' => $this->result->global_status,
            'mention' => $this->result->mention,
            'rank' => $this->result->rank,
            'acquired_credits' => $this->result->acquired_credits,
            'total_credits' => $this->result->total_credits,
            'publication_type' => $this->publicationRecord->publication_type,
            'published_at' => $this->result->published_at?->toIso8601String(),
        ];
    }
}
