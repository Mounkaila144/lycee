<?php

namespace Modules\Exams\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Exams\Entities\ExamAttendanceSheet;
use Modules\Exams\Entities\ExamSession;
use Modules\Exams\Services\ExamManagementService;

class ExamManagementController extends Controller
{
    public function __construct(private ExamManagementService $managementService) {}

    public function updateMaterials(Request $request, int $session): JsonResponse
    {
        $session = ExamSession::query()->findOrFail($session);

        $validated = $request->validate([
            'materials' => 'required|array',
        ]);

        $session = $this->managementService->updateAllowedMaterials($session, $validated['materials']);

        return response()->json($session);
    }

    public function updateInstructions(Request $request, int $session): JsonResponse
    {
        $session = ExamSession::query()->findOrFail($session);

        $validated = $request->validate([
            'instructions' => 'required|string',
        ]);

        $session = $this->managementService->updateInstructions($session, $validated['instructions']);

        return response()->json($session);
    }

    public function assignStudents(Request $request, int $session): JsonResponse
    {
        $session = ExamSession::query()->findOrFail($session);

        $validated = $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:tenant.students,id',
        ]);

        $sheets = $this->managementService->assignStudents($session, $validated['student_ids']);

        return response()->json($sheets, 201);
    }

    public function autoAssign(int $session): JsonResponse
    {
        $session = ExamSession::query()->findOrFail($session);
        $sheets = $this->managementService->autoAssignStudentsFromModule($session);

        return response()->json($sheets, 201);
    }

    public function reassignStudent(Request $request, int $sheet): JsonResponse
    {
        $sheet = ExamAttendanceSheet::query()->findOrFail($sheet);

        $validated = $request->validate([
            'room_assignment_id' => 'required|exists:tenant.exam_room_assignments,id',
            'seat_number' => 'nullable|string',
        ]);

        $sheet = $this->managementService->reassignStudent(
            $sheet,
            $validated['room_assignment_id'],
            $validated['seat_number'] ?? null
        );

        return response()->json($sheet);
    }

    public function removeStudent(int $sheet): JsonResponse
    {
        $sheet = ExamAttendanceSheet::query()->findOrFail($sheet);
        $this->managementService->removeStudent($sheet);

        return response()->json(null, 204);
    }

    public function eligibleStudents(int $session): JsonResponse
    {
        $session = ExamSession::query()->findOrFail($session);
        $students = $this->managementService->getEligibleStudents($session);

        return response()->json($students);
    }

    public function preparationChecklist(int $session): JsonResponse
    {
        $session = ExamSession::query()->findOrFail($session);
        $checklist = $this->managementService->generatePreparationChecklist($session);

        return response()->json($checklist);
    }
}
