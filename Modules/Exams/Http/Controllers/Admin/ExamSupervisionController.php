<?php

namespace Modules\Exams\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Exams\Entities\ExamAttendanceSheet;
use Modules\Exams\Entities\ExamSession;
use Modules\Exams\Entities\ExamSupervisor;
use Modules\Exams\Services\ExamSupervisionService;

class ExamSupervisionController extends Controller
{
    public function __construct(private ExamSupervisionService $supervisionService) {}

    public function assignSupervisors(Request $request, ExamSession $session): JsonResponse
    {
        $validated = $request->validate([
            'supervisors' => 'required|array',
            'supervisors.*.teacher_id' => 'required|exists:teachers,id',
            'supervisors.*.room_assignment_id' => 'nullable|exists:exam_room_assignments,id',
            'supervisors.*.role' => 'nullable|in:principal,assistant,reserve',
        ]);

        $supervisors = $this->supervisionService->assignMultipleSupervisors($session, $validated['supervisors']);

        return response()->json($supervisors, 201);
    }

    public function markPresent(ExamSupervisor $supervisor): JsonResponse
    {
        $supervisor = $this->supervisionService->markSupervisorPresent($supervisor);

        return response()->json($supervisor);
    }

    public function replaceSupervisor(Request $request, ExamSupervisor $supervisor): JsonResponse
    {
        $validated = $request->validate([
            'replacement_teacher_id' => 'required|exists:teachers,id',
        ]);

        $replacement = $this->supervisionService->replaceSupervisor($supervisor, $validated['replacement_teacher_id']);

        return response()->json($replacement, 201);
    }

    public function teacherSchedule(Request $request, int $teacher): JsonResponse
    {
        $schedule = $this->supervisionService->getSupervisorSchedule(
            $teacher,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json($schedule);
    }

    public function recordAttendance(Request $request, ExamAttendanceSheet $sheet): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:present,absent,late,excluded',
            'notes' => 'nullable|string',
        ]);

        $sheet = $this->supervisionService->recordStudentAttendance($sheet, $validated['status'], $validated);

        return response()->json($sheet);
    }

    public function recordSubmission(ExamAttendanceSheet $sheet): JsonResponse
    {
        $sheet = $this->supervisionService->recordSubmission($sheet);

        return response()->json($sheet);
    }

    public function verifySheet(ExamAttendanceSheet $sheet): JsonResponse
    {
        $sheet = $this->supervisionService->verifyAttendanceSheet($sheet, auth()->id());

        return response()->json($sheet);
    }

    public function attendanceStats(ExamSession $session): JsonResponse
    {
        $stats = $this->supervisionService->getAttendanceStatistics($session);

        return response()->json($stats);
    }
}
