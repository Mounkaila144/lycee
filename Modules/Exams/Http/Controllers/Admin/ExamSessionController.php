<?php

namespace Modules\Exams\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Exams\Entities\ExamSession;
use Modules\Exams\Services\ExamPlanningService;

class ExamSessionController extends Controller
{
    public function __construct(private ExamPlanningService $planningService) {}

    public function index(Request $request): JsonResponse
    {
        $query = ExamSession::query()->with(['module', 'evaluationPeriod', 'academicYear']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('module_id')) {
            $query->where('module_id', $request->module_id);
        }

        $sessions = $query->latest()->paginate(20);

        return response()->json($sessions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:tenant.modules,id',
            'evaluation_period_id' => 'nullable|exists:tenant.academic_periods,id',
            'academic_year_id' => 'required|exists:tenant.academic_years,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:normal,rattrapage,special',
            'exam_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'instructions' => 'nullable|string',
            'allowed_materials' => 'nullable|array',
        ]);

        $session = $this->planningService->createExamSession($validated);

        return response()->json($session->load(['module', 'evaluationPeriod']), 201);
    }

    public function show(int $session): JsonResponse
    {
        $session = ExamSession::query()->findOrFail($session);

        return response()->json($session->load(['module', 'roomAssignments.room', 'supervisors.teacher', 'attendanceSheets']));
    }

    public function update(Request $request, int $session): JsonResponse
    {
        $session = ExamSession::query()->findOrFail($session);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'exam_date' => 'sometimes|date',
            'start_time' => 'sometimes',
            'end_time' => 'sometimes',
            'instructions' => 'nullable|string',
            'allowed_materials' => 'nullable|array',
        ]);

        $session = $this->planningService->updateExamSession($session, $validated);

        return response()->json($session);
    }

    public function destroy(int $session): JsonResponse
    {
        ExamSession::query()->findOrFail($session)->delete();

        return response()->json(null, 204);
    }

    public function publish(int $session): JsonResponse
    {
        $session = $this->planningService->publishSession(ExamSession::query()->findOrFail($session));

        return response()->json($session);
    }

    public function cancel(Request $request, int $session): JsonResponse
    {
        $session = $this->planningService->cancelSession(ExamSession::query()->findOrFail($session), $request->input('reason'));

        return response()->json($session);
    }

    public function duplicate(Request $request, int $session): JsonResponse
    {
        $newSession = $this->planningService->duplicateSession(ExamSession::query()->findOrFail($session), $request->all());

        return response()->json($newSession, 201);
    }

    public function validateSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'module_id' => 'nullable|exists:tenant.modules,id',
            'room_ids' => 'nullable|array',
            'room_ids.*' => 'exists:tenant.rooms,id',
            'exclude_session_id' => 'nullable|exists:tenant.exam_sessions,id',
        ]);

        $conflicts = $this->planningService->validateSchedule($validated, $validated['exclude_session_id'] ?? null);

        return response()->json([
            'valid' => empty($conflicts),
            'conflicts' => $conflicts,
        ]);
    }

    public function availableRooms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
        ]);

        $rooms = $this->planningService->getAvailableRooms(
            $validated['date'],
            $validated['start_time'],
            $validated['end_time']
        );

        return response()->json($rooms);
    }
}
