<?php

namespace Modules\NotesEvaluations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\NotesEvaluations\Services\RetakeIdentificationService;

class IdentifyRetakesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public int $semesterId,
        public bool $sendNotifications = true
    ) {}

    public function handle(RetakeIdentificationService $service): void
    {
        Log::info("Starting retake identification for semester {$this->semesterId}");

        try {
            $result = $service->identify($this->semesterId, $this->sendNotifications);

            Log::info("Retake identification completed for semester {$this->semesterId}", $result);
        } catch (\Exception $e) {
            Log::error("Retake identification failed for semester {$this->semesterId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("IdentifyRetakesJob failed permanently for semester {$this->semesterId}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
