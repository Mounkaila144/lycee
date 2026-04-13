<?php

namespace Modules\NotesEvaluations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\NotesEvaluations\Services\EctsCalculationService;
use Modules\NotesEvaluations\Services\SemesterAverageService;

class RecalculateSemesterAveragesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $semesterId,
        public array $studentIds
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SemesterAverageService $semesterService, EctsCalculationService $ectsService): void
    {
        $startTime = microtime(true);

        foreach ($this->studentIds as $studentId) {
            $semesterService->calculateWithEliminatories($studentId, $this->semesterId);
            $ectsService->allocateCredits($studentId, $this->semesterId);
        }

        $duration = round(microtime(true) - $startTime, 2);

        Log::info('Semester averages recalculated', [
            'semester_id' => $this->semesterId,
            'students_count' => count($this->studentIds),
            'duration_seconds' => $duration,
        ]);
    }
}
