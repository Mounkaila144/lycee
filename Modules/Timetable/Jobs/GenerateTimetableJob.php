<?php

namespace Modules\Timetable\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Modules\Timetable\Events\TimetableGenerated;
use Modules\Timetable\Services\AutoGenerationService;

class GenerateTimetableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes

    public function __construct(
        private int $semesterId,
        private int $groupId,
        private int $userId,
        private string $strategy = 'balanced'
    ) {}

    public function handle(AutoGenerationService $service): void
    {
        try {
            $result = $service->generate($this->semesterId, $this->groupId, $this->strategy);

            // Stocker résultat en cache pour récupération frontend
            Cache::put(
                "timetable_generation:{$this->userId}:{$this->groupId}",
                $result->toArray(),
                now()->addMinutes(30)
            );

            // Broadcast événement (optionnel pour websockets)
            // event(new TimetableGenerated($this->userId, $result));
        } catch (\Exception $e) {
            // Stocker erreur en cache
            Cache::put(
                "timetable_generation:{$this->userId}:{$this->groupId}",
                [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error' => true,
                ],
                now()->addMinutes(30)
            );

            throw $e;
        }
    }
}
