<?php

namespace Modules\NotesEvaluations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\PublicationRecord;
use Modules\NotesEvaluations\Entities\SemesterResult;
use Modules\NotesEvaluations\Notifications\ResultsAvailableNotification;

class SendResultNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $publicationRecordId,
        public array $studentIds
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        $successCount = 0;
        $failedCount = 0;

        $record = PublicationRecord::find($this->publicationRecordId);

        if (! $record) {
            Log::warning('Publication record not found for notifications', [
                'record_id' => $this->publicationRecordId,
            ]);

            return;
        }

        foreach ($this->studentIds as $studentId) {
            try {
                $student = Student::find($studentId);
                $result = SemesterResult::where('student_id', $studentId)
                    ->where('semester_id', $record->semester_id)
                    ->first();

                if ($student && $result && $student->email) {
                    // Send notification
                    Notification::send(
                        $student,
                        new ResultsAvailableNotification($result, $record)
                    );
                    $successCount++;
                }
            } catch (\Exception $e) {
                $failedCount++;
                Log::error('Failed to send result notification', [
                    'student_id' => $studentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        Log::info('Result notifications sent', [
            'record_id' => $this->publicationRecordId,
            'total' => count($this->studentIds),
            'success' => $successCount,
            'failed' => $failedCount,
            'duration_seconds' => $duration,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendResultNotificationsJob failed', [
            'record_id' => $this->publicationRecordId,
            'error' => $exception->getMessage(),
        ]);
    }
}
