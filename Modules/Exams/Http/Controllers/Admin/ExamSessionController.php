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
        $query = ExamSession::on('tenant')->with(['classe', 'subject', 'creator']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        $sessions = $query->orderByDesc('exam_date')->orderBy('start_time')->paginate(50);

        return response()->json($sessions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|integer|exists:tenant.classes,id',
            'subject_id' => 'required|integer|exists:tenant.subjects,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|in:normal,rattrapage,special',
            'exam_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'room_name' => 'nullable|string|max:100',
            'instructions' => 'nullable|string',
        ]);

        $duration = (strtotime($validated['end_time']) - strtotime($validated['start_time'])) / 60;

        $session = ExamSession::create([
            'class_id' => $validated['class_id'],
            'subject_id' => $validated['subject_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'] ?? 'normal',
            'exam_date' => $validated['exam_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'duration_minutes' => (int) $duration,
            'total_capacity' => 0,
            'room_name' => $validated['room_name'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
            'status' => 'planned',
            'created_by' => auth('tenant')->id(),
        ]);

        return response()->json(['data' => $session->load(['classe', 'subject'])], 201);
    }

    public function show(ExamSession $session): JsonResponse
    {
        return response()->json($session->load(['module', 'roomAssignments.room', 'supervisors.teacher', 'attendanceSheets']));
    }

    public function update(Request $request, ExamSession $session): JsonResponse
    {
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
        ExamSession::on('tenant')->findOrFail($session)->delete();

        return response()->json(['message' => 'Examen supprimé.']);
    }

    public function publish(ExamSession $session): JsonResponse
    {
        $session = $this->planningService->publishSession($session);

        return response()->json($session);
    }

    public function cancel(Request $request, ExamSession $session): JsonResponse
    {
        $session = $this->planningService->cancelSession($session, $request->input('reason'));

        return response()->json($session);
    }

    public function duplicate(Request $request, ExamSession $session): JsonResponse
    {
        $newSession = $this->planningService->duplicateSession($session, $request->all());

        return response()->json($newSession, 201);
    }

    public function validateSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'module_id' => 'nullable|exists:modules,id',
            'room_ids' => 'nullable|array',
            'room_ids.*' => 'exists:rooms,id',
            'exclude_session_id' => 'nullable|exists:exam_sessions,id',
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
