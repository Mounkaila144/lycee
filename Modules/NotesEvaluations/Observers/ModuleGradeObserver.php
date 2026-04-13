<?php

namespace Modules\NotesEvaluations\Observers;

use Illuminate\Support\Facades\Cache;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Services\EctsCalculationService;
use Modules\NotesEvaluations\Services\SemesterAverageService;

class ModuleGradeObserver
{
    public function __construct(
        protected SemesterAverageService $semesterService,
        protected EctsCalculationService $ectsService
    ) {}

    /**
     * Handle the ModuleGrade "created" event.
     */
    public function created(ModuleGrade $moduleGrade): void
    {
        $this->recalculateSemester($moduleGrade);
    }

    /**
     * Handle the ModuleGrade "updated" event.
     */
    public function updated(ModuleGrade $moduleGrade): void
    {
        if ($moduleGrade->isDirty(['average', 'is_final'])) {
            $this->recalculateSemester($moduleGrade);
        }
    }

    /**
     * Recalculate semester average and ECTS
     */
    protected function recalculateSemester(ModuleGrade $moduleGrade): void
    {
        $studentId = $moduleGrade->student_id;
        $semesterId = $moduleGrade->semester_id;

        // Recalculate semester average with eliminatory checks
        $this->semesterService->calculateWithEliminatories($studentId, $semesterId);

        // Allocate ECTS credits
        $this->ectsService->allocateCredits($studentId, $semesterId);

        // Invalidate cache
        $this->semesterService->invalidateCache($studentId, $semesterId);
    }
}
