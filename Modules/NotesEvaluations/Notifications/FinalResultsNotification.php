<?php

namespace Modules\NotesEvaluations\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Entities\SemesterResult;

class FinalResultsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SemesterResult $result
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return match ($this->result->final_status) {
            'admitted' => $this->admittedMail($notifiable),
            'admitted_with_debts' => $this->admittedWithDebtsMail($notifiable),
            'deferred_final' => $this->deferredFinalMail($notifiable),
            'repeating' => $this->repeatingMail($notifiable),
            default => $this->defaultMail($notifiable),
        };
    }

    /**
     * Mail for fully admitted students
     */
    protected function admittedMail(object $notifiable): MailMessage
    {
        $semesterName = $this->result->semester?->name ?? 'ce semestre';

        return (new MailMessage)
            ->subject("Félicitations - Admission {$semesterName}")
            ->greeting("Félicitations {$notifiable->firstname}!")
            ->line("Vous êtes officiellement admis(e) au {$semesterName}.")
            ->line('')
            ->line("**Moyenne générale:** {$this->result->average}/20")
            ->line("**Mention:** {$this->result->mention}")
            ->line("**Crédits ECTS acquis:** {$this->result->acquired_credits}/{$this->result->total_credits}")
            ->line("**Classement:** {$this->result->rank_display}")
            ->line('')
            ->line('Votre attestation de réussite est disponible en téléchargement.')
            ->action('Télécharger mon attestation', url('/student/attestation'))
            ->line('')
            ->line('Vous pouvez dès à présent procéder à votre inscription pour la prochaine année académique.')
            ->line('Nous vous souhaitons une excellente continuation dans vos études.');
    }

    /**
     * Mail for students admitted with debts
     */
    protected function admittedWithDebtsMail(object $notifiable): MailMessage
    {
        $semesterName = $this->result->semester?->name ?? 'ce semestre';

        // Get failed modules
        $failedModules = ModuleGrade::where([
            'student_id' => $notifiable->id,
            'semester_id' => $this->result->semester_id,
        ])
            ->with('module')
            ->where(function ($q) {
                $q->where('average', '<', 10)
                    ->orWhereNull('average');
            })
            ->where('status', '!=', 'Compensated')
            ->get();

        $mail = (new MailMessage)
            ->subject("Admission avec dettes - {$semesterName}")
            ->greeting("Bonjour {$notifiable->firstname},")
            ->line("Vous êtes admis(e) au {$semesterName} avec dettes.")
            ->line('')
            ->line("**Moyenne générale:** {$this->result->average}/20")
            ->line("**Crédits ECTS acquis:** {$this->result->acquired_credits}/{$this->result->total_credits}")
            ->line("**Crédits manquants:** {$this->result->missing_credits}")
            ->line('');

        if ($failedModules->isNotEmpty()) {
            $mail->line('**Modules à reprendre:**');
            foreach ($failedModules as $moduleGrade) {
                $moduleName = $moduleGrade->module?->name ?? 'Module';
                $moduleCode = $moduleGrade->module?->code;
                $credits = $moduleGrade->module?->credits_ects ?? 0;
                $average = $moduleGrade->average !== null ? number_format($moduleGrade->average, 2) : 'ABS';
                $mail->line("- {$moduleCode} - {$moduleName}: {$average}/20 ({$credits} ECTS)");
            }
            $mail->line('');
        }

        $mail->line('Vous pouvez vous inscrire pour la prochaine année académique mais devrez repasser les modules non validés.')
            ->action('Consulter mes modules à reprendre', url('/student/debts'))
            ->line('Nous vous encourageons à poursuivre vos efforts.');

        return $mail;
    }

    /**
     * Mail for students with deferred final status
     */
    protected function deferredFinalMail(object $notifiable): MailMessage
    {
        $semesterName = $this->result->semester?->name ?? 'ce semestre';

        return (new MailMessage)
            ->subject("Résultats finaux - {$semesterName}")
            ->greeting("Bonjour {$notifiable->firstname},")
            ->line("Nous sommes au regret de vous informer que vous n'avez pas validé le {$semesterName}.")
            ->line('')
            ->line("**Moyenne générale:** {$this->result->average}/20")
            ->line("**Crédits ECTS acquis:** {$this->result->acquired_credits}/{$this->result->total_credits}")
            ->line("**Taux de réussite:** {$this->result->success_rate}%")
            ->line('')
            ->line('Vous devrez vous réinscrire pour l\'année académique suivante.')
            ->action('Informations redoublement', url('/student/redoublement'))
            ->line('')
            ->line('N\'hésitez pas à contacter la scolarité pour plus d\'informations sur les modalités de redoublement.');
    }

    /**
     * Mail for students with repeating status (jury decision)
     */
    protected function repeatingMail(object $notifiable): MailMessage
    {
        $semesterName = $this->result->semester?->name ?? 'ce semestre';

        return (new MailMessage)
            ->subject("Décision du jury - {$semesterName}")
            ->greeting("Bonjour {$notifiable->firstname},")
            ->line("Suite à la délibération du jury, il a été décidé que vous devez redoubler le {$semesterName}.")
            ->line('')
            ->line("**Moyenne générale:** {$this->result->average}/20")
            ->line("**Crédits ECTS acquis:** {$this->result->acquired_credits}/{$this->result->total_credits}")
            ->line('')
            ->line('Cette décision a été prise en tenant compte de l\'ensemble de votre parcours académique.')
            ->action('Informations redoublement', url('/student/redoublement'))
            ->line('')
            ->line('Vous pouvez contacter la scolarité si vous souhaitez des informations complémentaires ou si vous pensez qu\'il y a une erreur.');
    }

    /**
     * Default mail
     */
    protected function defaultMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Résultats finaux disponibles')
            ->greeting("Bonjour {$notifiable->firstname},")
            ->line('Vos résultats finaux sont disponibles.')
            ->line("**Moyenne:** {$this->result->average}/20")
            ->line("**Crédits ECTS:** {$this->result->acquired_credits}/{$this->result->total_credits}")
            ->action('Consulter mes résultats', url('/student/results'))
            ->line('N\'hésitez pas à nous contacter pour toute question.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'final_results',
            'semester_id' => $this->result->semester_id,
            'semester_result_id' => $this->result->id,
            'final_status' => $this->result->final_status,
            'final_status_label' => $this->result->final_status_label,
            'average' => $this->result->average,
            'acquired_credits' => $this->result->acquired_credits,
            'total_credits' => $this->result->total_credits,
            'can_progress_next_year' => $this->result->can_progress_next_year,
            'attestation_available' => $this->result->attestation_file_path !== null,
            'published_at' => now()->toIso8601String(),
        ];
    }
}
