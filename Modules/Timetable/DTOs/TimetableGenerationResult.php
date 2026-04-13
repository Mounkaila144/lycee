<?php

namespace Modules\Timetable\DTOs;

class TimetableGenerationResult
{
    public function __construct(
        public bool $success,
        public ?array $slots = null,
        public ?float $score = null,
        public array $conflicts = [],
        public ?array $statistics = null,
        public ?string $message = null,
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'slots' => $this->slots,
            'score' => $this->score,
            'conflicts' => $this->conflicts,
            'statistics' => $this->statistics,
            'message' => $this->message,
        ];
    }
}
