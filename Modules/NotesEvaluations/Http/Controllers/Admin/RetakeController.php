<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Entities\RetakeEnrollment;
use Modules\NotesEvaluations\Exports\RetakeStudentsExport;
use Modules\NotesEvaluations\Http\Resources\RetakeEnrollmentResource;
use Modules\NotesEvaluations\Http\Resources\RetakeStatisticsResource;
use Modules\NotesEvaluations\Jobs\IdentifyRetakesJob;
use Modules\NotesEvaluations\Services\RetakeIdentificationService;

class RetakeController extends Controller
{
    public function __construct(
        protected RetakeIdentificationService $retakeService
    ) {}

    /**
     * Trigger retake identification for a semester
     * POST /api/admin/semesters/{semester}/identify-retakes
     */
    public function identify(Request $request, int $semesterId): JsonResponse
    {
        $async = $request->boolean('async', true);
        $sendNotifications = $request->boolean('send_notifications', true);

        if ($async) {
            IdentifyRetakesJob::dispatch($semesterId, $sendNotifications);

            return response()->json([
                'message' => 'Identification des rattrapages lancée en arrière-plan.',
                'status' => 'queued',
            ], 202);
        }

        $result = $this->retakeService->identify($semesterId, $sendNotifications);

        return response()->json([
            'message' => 'Identification des rattrapages terminée.',
            'data' => $result,
        ]);
    }

    /**
     * Get retake statistics for a semester
     * GET /api/admin/semesters/{semester}/retake-statistics
     */
    public function statistics(int $semesterId): JsonResponse
    {
        $statistics = $this->retakeService->getStatistics($semesterId);

        return response()->json([
            'data' => new RetakeStatisticsResource($statistics),
        ]);
    }

    /**
     * Get list of students with retakes for a semester
     * GET /api/admin/semesters/{semester}/retake-students
     */
    public function studentsList(Request $request, int $semesterId): JsonResponse
    {
        $status = $request->input('status');
        $students = $this->retakeService->getStudentsWithRetakes($semesterId, $status);

        // Pagination manuelle
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);
        $total = $students->count();

        $paginatedStudents = $students->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => $paginatedStudents,
            'meta' => [
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Get list of modules with retakes for a semester
     * GET /api/admin/semesters/{semester}/retake-modules
     */
    public function modulesList(int $semesterId): JsonResponse
    {
        $modules = $this->retakeService->getModulesWithRetakes($semesterId);

        return response()->json([
            'data' => $modules,
        ]);
    }

    /**
     * Get students eligible for retake in a specific module
     * GET /api/admin/modules/{module}/retake-students
     */
    public function moduleRetakeStudents(Request $request, int $moduleId): JsonResponse
    {
        $semesterId = $request->input('semester_id');

        if (! $semesterId) {
            return response()->json([
                'message' => 'semester_id is required',
            ], 422);
        }

        $students = $this->retakeService->getStudentsByModule($moduleId, $semesterId);

        return response()->json([
            'data' => RetakeEnrollmentResource::collection($students),
        ]);
    }

    /**
     * Get retake modules for a specific student
     * GET /api/admin/students/{student}/retake-modules
     */
    public function studentRetakeModules(Request $request, int $studentId): JsonResponse
    {
        $semesterId = $request->input('semester_id');

        $modules = $this->retakeService->getModulesByStudent($studentId, $semesterId);

        return response()->json([
            'data' => RetakeEnrollmentResource::collection($modules),
        ]);
    }

    /**
     * Get eligible students for retake (all in semester with to_retake status)
     * GET /api/admin/semesters/{semester}/retake-eligible
     */
    public function eligibleStudents(Request $request, int $semesterId): JsonResponse
    {
        $students = $this->retakeService->getStudentsWithRetakes($semesterId, 'pending');

        return response()->json([
            'data' => $students,
            'meta' => [
                'total' => $students->count(),
            ],
        ]);
    }

    /**
     * Show a specific retake enrollment
     * GET /api/admin/retake-enrollments/{retakeEnrollment}
     */
    public function show(RetakeEnrollment $retakeEnrollment): JsonResponse
    {
        $retakeEnrollment->load(['student', 'module', 'semester']);

        return response()->json([
            'data' => new RetakeEnrollmentResource($retakeEnrollment),
        ]);
    }

    /**
     * Schedule a retake
     * POST /api/admin/retake-enrollments/{retakeEnrollment}/schedule
     */
    public function schedule(Request $request, RetakeEnrollment $retakeEnrollment): JsonResponse
    {
        if (! $retakeEnrollment->canBeScheduled()) {
            return response()->json([
                'message' => 'Ce rattrapage ne peut pas être programmé dans son état actuel.',
            ], 422);
        }

        $retakeEnrollment->schedule($request->input('scheduled_at'));

        return response()->json([
            'message' => 'Rattrapage programmé avec succès.',
            'data' => new RetakeEnrollmentResource($retakeEnrollment->fresh(['student', 'module'])),
        ]);
    }

    /**
     * Cancel a retake enrollment
     * POST /api/admin/retake-enrollments/{retakeEnrollment}/cancel
     */
    public function cancel(RetakeEnrollment $retakeEnrollment): JsonResponse
    {
        if ($retakeEnrollment->isCancelled() || $retakeEnrollment->isValidated()) {
            return response()->json([
                'message' => 'Ce rattrapage ne peut pas être annulé.',
            ], 422);
        }

        $retakeEnrollment->cancel();

        return response()->json([
            'message' => 'Rattrapage annulé.',
            'data' => new RetakeEnrollmentResource($retakeEnrollment->fresh(['student', 'module'])),
        ]);
    }

    /**
     * Export retake students list
     * GET /api/admin/semesters/{semester}/retake-students/export
     */
    public function export(Request $request, int $semesterId): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $moduleId = $request->input('module_id');

        return (new RetakeStudentsExport($semesterId, $moduleId))
            ->download("rattrapages_semestre_{$semesterId}.xlsx");
    }
}
