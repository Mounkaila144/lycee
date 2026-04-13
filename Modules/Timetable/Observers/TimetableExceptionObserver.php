<?php

namespace Modules\Timetable\Observers;

use Modules\Timetable\Entities\TimetableException;
use Modules\Timetable\Jobs\NotifyTimetableChangeJob;

/**
 * Observer pour déclencher les notifications automatiques
 * lors de modifications d'emploi du temps
 */
class TimetableExceptionObserver
{
    /**
     * Handle the TimetableException "created" event.
     */
    public function created(TimetableException $exception): void
    {
        // Only notify if notify_students flag is true
        if (! $exception->notify_students) {
            return;
        }

        $changeType = $this->determineChangeType($exception);

        // Dispatch notification job
        NotifyTimetableChangeJob::dispatch($exception, $changeType)
            ->onQueue('notifications');
    }

    /**
     * Handle the TimetableException "updated" event.
     */
    public function updated(TimetableException $exception): void
    {
        // If exception was restored from soft delete, re-notify
        if ($exception->wasChanged('deleted_at') && $exception->deleted_at === null) {
            $this->created($exception);
        }
    }

    /**
     * Determine the type of change based on exception data
     */
    private function determineChangeType(TimetableException $exception): string
    {
        $exceptionType = $exception->exception_type ?? 'modification';

        // Check for cancellation
        if ($exceptionType === 'annulation' || $exceptionType === 'cancellation') {
            return 'cancellation';
        }

        // Check for teacher replacement
        $newValues = $exception->new_values ?? [];
        $originalValues = $exception->original_values ?? [];

        if (isset($newValues['teacher_id']) && isset($originalValues['teacher_id'])) {
            if ($newValues['teacher_id'] !== $originalValues['teacher_id']) {
                return 'replacement';
            }
        }

        // Default to general change
        return 'change';
    }
}
