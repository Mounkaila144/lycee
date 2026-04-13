<?php

namespace Modules\NotesEvaluations\Observers;

use Modules\NotesEvaluations\Entities\SemesterResult;
use Modules\NotesEvaluations\Services\EctsCalculationService;

class SemesterResultObserver
{
    public function __construct(
        protected EctsCalculationService $ectsService
    ) {}

    /**
     * Handle the SemesterResult "updated" event.
     */
    public function updated(SemesterResult $semesterResult): void
    {
        if ($semesterResult->isDirty(['average', 'is_final', 'is_validated'])) {
            $this->ectsService->allocateCredits(
                $semesterResult->student_id,
                $semesterResult->semester_id
            );
        }
    }
}
