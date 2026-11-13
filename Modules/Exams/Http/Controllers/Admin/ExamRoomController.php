<?php

namespace Modules\Exams\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Exams\Entities\ExamRoomAssignment;
use Modules\Exams\Entities\ExamSession;
use Modules\Exams\Services\ExamPlanningService;

class ExamRoomController extends Controller
{
    public function __construct(private ExamPlanningService $planningService) {}

    public function assign(Request $request, int $session): JsonResponse
    {
        $session = ExamSession::query()->findOrFail($session);

        $validated = $request->validate([
            'rooms' => 'required|array',
            'rooms.*.room_id' => 'required|exists:tenant.rooms,id',
            'rooms.*.capacity' => 'nullable|integer',
            'rooms.*.seat_start_number' => 'nullable|integer',
            'rooms.*.seat_end_number' => 'nullable|integer',
        ]);

        $assignments = $this->planningService->assignMultipleRooms($session, $validated['rooms']);

        return response()->json($assignments, 201);
    }

    public function remove(int $session, int $assignment): JsonResponse
    {
        ExamRoomAssignment::query()
            ->where('exam_session_id', $session)
            ->findOrFail($assignment)
            ->delete();

        return response()->json(null, 204);
    }
}
