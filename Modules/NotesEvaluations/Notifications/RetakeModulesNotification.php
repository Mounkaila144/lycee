<?php

namespace Modules\NotesEvaluations\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class RetakeModulesNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Collection $retakeModules
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->retakeModules->count();
        $moduleWord = $count > 1 ? 'modules' : 'module';

        $mail = (new MailMessage)
            ->subject("Session de rattrapage - {$count} {$moduleWord}")
            ->greeting("Bonjour {$notifiable->firstname},")
            ->line("Suite à la publication des résultats de votre semestre, vous devez passer des épreuves de rattrapage pour {$count} {$moduleWord}.")
            ->line('');

        // List modules with grades
        $mail->line('**Modules concernés:**');
        foreach ($this->retakeModules as $retake) {
            $moduleName = $retake->module?->name ?? 'Module inconnu';
            $average = $retake->original_average !== null
                ? number_format($retake->original_average, 2).'/20'
                : 'ABS';
            $mail->line("- {$moduleName}: {$average}");
        }

        $mail->line('')
            ->line('Vous serez convoqué prochainement pour la session de rattrapage. Veuillez consulter régulièrement votre espace étudiant pour les dates et modalités.')
            ->action('Consulter mes rattrapages', url('/student/retakes'))
            ->line('')
            ->line('Nous vous conseillons de bien préparer cette session. Bon courage !');

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'retake_modules',
            'count' => $this->retakeModules->count(),
            'modules' => $this->retakeModules->map(function ($retake) {
                return [
                    'retake_enrollment_id' => $retake->id,
                    'module_id' => $retake->module_id,
                    'module_name' => $retake->module?->name,
                    'original_average' => $retake->original_average,
                ];
            })->values()->toArray(),
            'identified_at' => now()->toIso8601String(),
        ];
    }
}
