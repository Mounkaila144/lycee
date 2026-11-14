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

    public function assignSupervisors(Request $request, int $session): JsonResponse
    {
        $session = ExamSession::query()->findOrFail($session);

        $validated = $request->validate([
            'supervisors' => 'required|array',
            'supervisors.*.teacher_id' => 'required|exists:tenant.users,id',
            'supervisors.*.room_assignment_id' => 'nullable|exists:tenant.exam_room_assignments,id',
            'supervisors.*.role' => 'nullable|in:principal,assistant,reserve',
        ]);

        $supervisors = $this->supervisionService->assignMultipleSupervisors($session, $validated['supervisors']);

        return response()->json($supervisors, 201);
    }

    /**
     * Un professeur ne pointe que sa propre présence ; Administrator/Manager pointent tout surveillant.
     */
    public function markPresent(Request $request, int $supervisor): JsonResponse
    {
        $supervisor = ExamSupervisor::query()->findOrFail($supervisor);
        $user = $request->user();

        if (! $user->hasAnyRole(['Administrator', 'Manager']) && (int) $supervisor->teacher_id !== (int) $user->id) {
            return response()->json(['message' => 'Vous ne pouvez pointer que votre propre présence.'], 403);
        }
        $supervisor = $this->supervisionService->markSupervisorPresent($supervisor);

        return response()->json($supervisor);
    }

    public function replaceSupervisor(Request $request, int $supervisor): JsonResponse
    {
        $supervisor = ExamSupervisor::query()->findOrFail($supervisor);

        $validated = $request->validate([
            'replacement_teacher_id' => 'required|exists:tenant.users,id',
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

    public function recordAttendance(Request $request, int $sheet): JsonResponse
    {
        $sheet = ExamAttendanceSheet::query()->findOrFail($sheet);

        $validated = $request->validate([
            'status' => 'required|in:present,absent,late,excluded',
            'notes' => 'nullable|string',
        ]);

        $sheet = $this->supervisionService->recordStudentAttendance($sheet, $validated['status'], $validated);

        return response()->json($sheet);
    }

    public function recordSubmission(int $sheet): JsonResponse
    {
        $sheet = ExamAttendanceSheet::query()->findOrFail($sheet);
        $sheet = $this->supervisionService->recordSubmission($sheet);

        return response()->json($sheet);
    }

    public function verifySheet(Request $request, int $sheet): JsonResponse
    {
        $sheet = ExamAttendanceSheet::query()->findOrFail($sheet);
        $sheet = $this->supervisionService->verifyAttendanceSheet($sheet, (int) $request->user()->id);

        return response()->json($sheet);
    }

    public function attendanceStats(int $session): JsonResponse
    {
        $session = ExamSession::query()->findOrFail($session);
        $stats = $this->supervisionService->getAttendanceStatistics($session);

        return response()->json($stats);
    }
}
