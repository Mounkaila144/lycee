<?php

namespace Modules\Timetable\DTOs;

class DuplicationResult
{
    public function __construct(
        public bool $success,
        public ?array $newSlots = null,
        public ?array $report = null,
        public ?string $message = null,
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'newSlots' => $this->newSlots,
            'report' => $this->report,
            'message' => $this->message,
        ];
    }
}
