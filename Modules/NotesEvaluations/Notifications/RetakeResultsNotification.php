<?php

namespace Modules\NotesEvaluations\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Entities\RetakeEnrollment;
use Modules\NotesEvaluations\Entities\SemesterResult;

class RetakeResultsNotification extends Notification implements ShouldQueue
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
        $retakeResults = RetakeEnrollment::where([
            'student_id' => $notifiable->id,
            'semester_id' => $this->result->semester_id,
        ])
            ->with(['module', 'retakeGrade'])
            ->get();

        // Count modules passed after retake
        $passed = $retakeResults->filter(function ($enrollment) {
            $moduleGrade = ModuleGrade::where([
                'student_id' => $enrollment->student_id,
                'module_id' => $enrollment->module_id,
                'semester_id' => $enrollment->semester_id,
            ])->first();

            return $moduleGrade && $moduleGrade->average >= 10;
        })->count();

        $total = $retakeResults->count();

        $mail = (new MailMessage)
            ->subject('Résultats de votre session de rattrapage')
            ->greeting("Bonjour {$notifiable->firstname},")
            ->line('Vos résultats de la session de rattrapage sont disponibles.')
            ->line('');

        // Summary
        $mail->line("**Modules validés après rattrapage:** {$passed}/{$total}");
        $mail->line("**Nouveau statut semestre:** {$this->result->global_status_label}");
        $mail->line("**Crédits ECTS acquis:** {$this->result->acquired_credits}/{$this->result->total_credits}");
        $mail->line('');

        // Detail per module
        $mail->line('**Détail par module:**');
        foreach ($retakeResults as $enrollment) {
            $moduleGrade = ModuleGrade::where([
                'student_id' => $enrollment->student_id,
                'module_id' => $enrollment->module_id,
                'semester_id' => $enrollment->semester_id,
            ])->first();

            $moduleName = $enrollment->module?->name ?? 'Module';
            $retakeScore = $enrollment->retakeGrade?->score;
            $newAverage = $moduleGrade?->average;
            $status = $newAverage >= 10 ? '✅ Validé' : '❌ Non validé';

            if ($retakeScore !== null) {
                $mail->line("- {$moduleName}: {$enrollment->original_average} → {$retakeScore} (Nouvelle moyenne: {$newAverage}) - {$status}");
            } else {
                $mail->line("- {$moduleName}: Absent au rattrapage - {$status}");
            }
        }

        $mail->line('')
            ->action('Consulter mes résultats', url('/student/retake-results'))
            ->line('Nous vous souhaitons bonne continuation dans vos études.');

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'retake_results',
            'semester_id' => $this->result->semester_id,
            'semester_result_id' => $this->result->id,
            'global_status' => $this->result->global_status,
            'average' => $this->result->average,
            'acquired_credits' => $this->result->acquired_credits,
            'total_credits' => $this->result->total_credits,
            'published_at' => now()->toIso8601String(),
        ];
    }
}
