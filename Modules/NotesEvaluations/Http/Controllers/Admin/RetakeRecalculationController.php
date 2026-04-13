<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Services\RetakeRecalculationService;

class RetakeRecalculationController extends Controller
{
    public function __construct(
        protected RetakeRecalculationService $recalculationService
    ) {}

    /**
     * Recalculate all students in a semester after retake
     * POST /api/admin/semesters/{semester}/recalculate-after-retake
     */
    public function recalculateAll(Request $request, int $semesterId): JsonResponse
    {
        $async = $request->boolean('async', true);

        $result = $this->recalculationService->recalculateAllStudents($semesterId, $async);

        return response()->json([
            'message' => $result['message'],
            'data' => $result,
        ], $result['status'] ?? 'queued' === 'queued' ? 202 : 200);
    }

    /**
     * Recalculate for a specific student
     * POST /api/admin/students/{student}/recalculate-retake
     */
    public function recalculateStudent(Request $request, int $studentId): JsonResponse
    {
        $semesterId = $request->input('semester_id');

        if (! $semesterId) {
            return response()->json([
                'message' => 'semester_id is required',
            ], 422);
        }

        $result = $this->recalculationService->recalculateAfterRetake($studentId, $semesterId);

        return response()->json([
            'message' => 'Recalcul terminé pour l\'étudiant.',
            'data' => $result,
        ]);
    }

    /**
     * Get recalculation logs for a semester
     * GET /api/admin/semesters/{semester}/recalculation-logs
     */
    public function logs(Request $request, int $semesterId): JsonResponse
    {
        $studentId = $request->query('student_id');

        $logs = $this->recalculationService->getRecalculationLogs($semesterId, $studentId);

        return response()->json([
            'data' => $logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'student_id' => $log->student_id,
                    'student' => $log->student ? [
                        'matricule' => $log->student->matricule,
                        'full_name' => $log->student->firstname.' '.$log->student->lastname,
                    ] : null,
                    'trigger' => $log->trigger,
                    'trigger_label' => $log->trigger_label,
                    'old_semester_average' => $log->old_semester_average,
                    'new_semester_average' => $log->new_semester_average,
                    'semester_average_change' => $log->semester_average_change,
                    'old_status' => $log->old_semester_status,
                    'new_status' => $log->new_semester_status,
                    'credits_before' => $log->credits_before,
                    'credits_after' => $log->credits_after,
                    'credits_gained' => $log->credits_gained,
                    'modules_updated' => $log->details['modules_updated'] ?? 0,
                    'recalculated_at' => $log->recalculated_at?->toIso8601String(),
                ];
            }),
            'meta' => [
                'total' => $logs->count(),
            ],
        ]);
    }
}
