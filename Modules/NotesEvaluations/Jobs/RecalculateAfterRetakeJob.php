<?php

namespace Modules\NotesEvaluations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\NotesEvaluations\Services\RetakeRecalculationService;

class RecalculateAfterRetakeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    public function __construct(
        public int $semesterId,
        public array $studentIds
    ) {}

    public function handle(RetakeRecalculationService $service): void
    {
        Log::info("Starting batch retake recalculation for semester {$this->semesterId}", [
            'student_count' => count($this->studentIds),
        ]);

        $successful = 0;
        $failed = 0;

        foreach ($this->studentIds as $studentId) {
            try {
                $service->recalculateAfterRetake($studentId, $this->semesterId);
                $successful++;
            } catch (\Exception $e) {
                $failed++;
                Log::error("Retake recalculation failed for student {$studentId}", [
                    'semester_id' => $this->semesterId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Batch retake recalculation completed for semester {$this->semesterId}", [
            'successful' => $successful,
            'failed' => $failed,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("RecalculateAfterRetakeJob failed permanently for semester {$this->semesterId}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
