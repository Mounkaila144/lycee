<?php

namespace Modules\Timetable\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Timetable\Entities\TimetableException;
use Modules\Timetable\Services\ExceptionManagementService;

class TimetableExceptionController extends Controller
{
    public function __construct(
        private ExceptionManagementService $exceptionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = TimetableException::with(['timetableSlot.module', 'creator']);

        if ($request->has('semester_id')) {
            $query->whereHas('timetableSlot', fn ($q) => $q->where('semester_id', $request->semester_id));
        }

        if ($request->has('exception_type')) {
            $query->where('exception_type', $request->exception_type);
        }

        $exceptions = $query->paginate($request->get('per_page', 15));

        return response()->json($exceptions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'timetable_slot_id' => 'required|integer|exists:tenant.timetable_slots,id',
            'exception_date' => 'required|date',
            'exception_type' => ['required', 'string', Rule::in([
                'cancellation',
                'room_change',
                'teacher_replacement',
                'time_change',
                'date_change',
                'exceptional_session',
            ])],
            'new_values' => 'nullable|array',
            'reason' => 'required|string|max:1000',
            'notify_students' => 'nullable|boolean',
        ]);

        $exception = $this->exceptionService->createException(
            $validated['timetable_slot_id'],
            Carbon::parse($validated['exception_date']),
            $validated['exception_type'],
            $validated['new_values'] ?? [],
            $validated['reason'],
            $validated['notify_students'] ?? true
        );

        return response()->json($exception->load(['timetableSlot', 'creator']), 201);
    }

    public function getSlotHistory(int $slotId): JsonResponse
    {
        $history = $this->exceptionService->getSlotExceptionsHistory($slotId);

        return response()->json($history);
    }

    public function getUpcoming(Request $request, int $semesterId): JsonResponse
    {
        $days = $request->get('days', 7);
        $exceptions = $this->exceptionService->getUpcomingExceptions($semesterId, $days);

        return response()->json($exceptions);
    }

    public function destroy(TimetableException $exception): JsonResponse
    {
        $this->exceptionService->cancelException($exception->id);

        return response()->json(['message' => 'Exception annulée avec succès'], 200);
    }
}
