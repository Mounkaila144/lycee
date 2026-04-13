<?php

namespace Modules\Timetable\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Timetable\Entities\TimetableException;
use Modules\Timetable\Entities\TimetableSlot;

class ExceptionManagementService
{
    public function __construct(
        private ConflictDetectionService $conflictDetector
    ) {}

    public function createException(
        int $timetableSlotId,
        Carbon $exceptionDate,
        string $exceptionType,
        array $newValues,
        string $reason,
        bool $notifyStudents = true
    ): TimetableException {
        $slot = TimetableSlot::findOrFail($timetableSlotId);

        // Vérifier limite exceptions
        $existingExceptions = TimetableException::where('timetable_slot_id', $timetableSlotId)
            ->whereNull('deleted_at')
            ->count();

        if ($existingExceptions >= config('timetable.max_exceptions_per_slot', 3)) {
            throw new \Exception('Nombre maximal d\'exceptions atteint pour cette séance.');
        }

        // Sauvegarder valeurs originales
        $originalValues = [
            'room_id' => $slot->room_id,
            'teacher_id' => $slot->teacher_id,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'day_of_week' => $slot->day_of_week,
        ];

        $exception = DB::connection('tenant')->transaction(function () use (
            $slot,
            $exceptionDate,
            $exceptionType,
            $originalValues,
            $newValues,
            $reason,
            $notifyStudents
        ) {
            return TimetableException::create([
                'timetable_slot_id' => $slot->id,
                'exception_date' => $exceptionDate,
                'exception_type' => $exceptionType,
                'original_values' => $originalValues,
                'new_values' => $newValues,
                'reason' => $reason,
                'notify_students' => $notifyStudents,
                'created_by' => auth()->id(),
            ]);
        });

        return $exception;
    }

    public function cancelException(int $exceptionId): void
    {
        $exception = TimetableException::findOrFail($exceptionId);
        $exception->delete(); // Soft delete
    }

    public function getSlotExceptionsHistory(int $timetableSlotId): Collection
    {
        return TimetableException::where('timetable_slot_id', $timetableSlotId)
            ->withTrashed()
            ->with('creator')
            ->orderBy('exception_date', 'desc')
            ->get();
    }

    public function getUpcomingExceptions(int $semesterId, int $days = 7): Collection
    {
        return TimetableException::whereHas('timetableSlot', function ($q) use ($semesterId) {
            $q->where('semester_id', $semesterId);
        })
            ->whereBetween('exception_date', [now(), now()->addDays($days)])
            ->with(['timetableSlot.module', 'timetableSlot.teacher', 'timetableSlot.room'])
            ->orderBy('exception_date')
            ->get();
    }
}
