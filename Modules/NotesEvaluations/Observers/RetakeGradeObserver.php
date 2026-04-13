<?php

namespace Modules\NotesEvaluations\Observers;

use Modules\NotesEvaluations\Entities\RetakeGrade;
use Modules\NotesEvaluations\Services\RetakeRecalculationService;

class RetakeGradeObserver
{
    public function __construct(
        protected RetakeRecalculationService $recalculationService
    ) {}

    /**
     * Handle the RetakeGrade "updated" event.
     */
    public function updated(RetakeGrade $retakeGrade): void
    {
        // Only trigger recalculation when status changes to 'published'
        if ($retakeGrade->isDirty('status') && $retakeGrade->status === 'published') {
            $enrollment = $retakeGrade->retakeEnrollment;

            if ($enrollment) {
                $this->recalculationService->recalculateAfterRetake(
                    $enrollment->student_id,
                    $enrollment->semester_id
                );
            }
        }
    }
}
