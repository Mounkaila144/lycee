<?php

namespace Modules\NotesEvaluations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\NotesEvaluations\Entities\GradeValidation;
use Modules\NotesEvaluations\Services\GradeValidationService;

class PublishGradesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $validationId
    ) {}

    public function handle(GradeValidationService $service): void
    {
        $validation = GradeValidation::find($this->validationId);

        if (! $validation || ! $validation->canBePublished()) {
            return;
        }

        $service->publishGrades($validation);
    }
}
