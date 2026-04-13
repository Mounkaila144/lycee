<?php

namespace Modules\NotesEvaluations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\NotesEvaluations\Services\ModuleAverageService;

class RecalculateModuleAveragesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $moduleId,
        public int $semesterId,
        public array $studentIds
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ModuleAverageService $service): void
    {
        $startTime = microtime(true);

        foreach ($this->studentIds as $studentId) {
            $service->calculate($studentId, $this->moduleId, $this->semesterId);
        }

        $duration = round(microtime(true) - $startTime, 2);

        Log::info('Module averages recalculated', [
            'module_id' => $this->moduleId,
            'semester_id' => $this->semesterId,
            'students_count' => count($this->studentIds),
            'duration_seconds' => $duration,
        ]);
    }
}
