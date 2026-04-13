<?php

namespace Modules\Attendance\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Attendance\Services\AttendanceMonitoringService;

class AttendanceMonitoringController extends Controller
{
    public function __construct(
        private AttendanceMonitoringService $monitoringService
    ) {}

    /**
     * Vérifier seuils étudiant (Story 08)
     */
    public function checkThresholds(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:tenant.users,id',
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $alert = $this->monitoringService->checkThresholdsForStudent(
            $validated['student_id'],
            $validated['semester_id']
        );

        return response()->json([
            'alert_created' => $alert !== null,
            'alert' => $alert,
        ]);
    }

    /**
     * Déclencher alertes automatiques (Story 09)
     */
    public function triggerAlerts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $alerts = $this->monitoringService->triggerAutomaticAlerts($validated['semester_id']);

        return response()->json([
            'message' => sprintf('%d alertes créées', $alerts->count()),
            'alerts' => $alerts,
        ]);
    }

    /**
     * Obtenir alertes actives
     */
    public function getActiveAlerts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $alerts = $this->monitoringService->getActiveAlerts($validated['semester_id']);

        return response()->json($alerts);
    }

    /**
     * Historique présences étudiant (Story 10)
     */
    public function getStudentHistory(Request $request, int $studentId): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $history = $this->monitoringService->getStudentHistory(
            $studentId,
            $validated['semester_id']
        );

        return response()->json($history);
    }

    /**
     * Statistiques étudiant
     */
    public function getStudentStats(Request $request, int $studentId): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:tenant.semesters,id',
        ]);

        $stats = $this->monitoringService->calculateStudentStats(
            $studentId,
            $validated['semester_id']
        );

        return response()->json($stats);
    }
}
