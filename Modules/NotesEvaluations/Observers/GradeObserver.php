<?php

namespace Modules\NotesEvaluations\Observers;

use Illuminate\Support\Facades\Cache;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\NotesEvaluations\Entities\GradeHistory;
use Modules\NotesEvaluations\Services\ModuleAverageService;

class GradeObserver
{
    public function __construct(
        protected ModuleAverageService $averageService
    ) {}

    /**
     * Handle the Grade "created" event.
     */
    public function created(Grade $grade): void
    {
        $this->logHistory($grade, 'creation');
        $this->recalculateModuleAverage($grade);
    }

    /**
     * Handle the Grade "updated" event.
     */
    public function updated(Grade $grade): void
    {
        // Only log if score or is_absent changed
        if (! $grade->isDirty(['score', 'is_absent'])) {
            return;
        }

        $changeType = $grade->isPublished() ? 'correction' : 'modification';
        $this->logHistory($grade, $changeType);
        $this->recalculateModuleAverage($grade);
    }

    /**
     * Log grade history
     */
    protected function logHistory(Grade $grade, string $changeType): void
    {
        GradeHistory::create([
            'grade_id' => $grade->id,
            'old_value' => $changeType === 'creation' ? null : $grade->getOriginal('score'),
            'new_value' => $grade->score,
            'old_is_absent' => $changeType === 'creation' ? false : $grade->getOriginal('is_absent'),
            'new_is_absent' => $grade->is_absent,
            'changed_by' => auth()->id() ?? $grade->entered_by,
            'changed_at' => now(),
            'change_type' => $changeType,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Recalculate module average after grade change
     */
    protected function recalculateModuleAverage(Grade $grade): void
    {
        $evaluation = $grade->evaluation;

        if (! $evaluation) {
            return;
        }

        $moduleId = $evaluation->module_id;
        $semesterId = $evaluation->semester_id;
        $studentId = $grade->student_id;

        // Recalculate module average
        $this->averageService->calculate($studentId, $moduleId, $semesterId);

        // Invalidate cache
        $this->averageService->invalidateCache($studentId, $moduleId, $semesterId);
    }
}
