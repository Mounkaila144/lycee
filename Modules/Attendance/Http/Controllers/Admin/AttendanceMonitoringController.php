<?php

namespace Modules\Attendance\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Attendance\Services\AttendanceAccessService;
use Modules\Attendance\Services\AttendanceMonitoringService;

class AttendanceMonitoringController extends Controller
{
    public function __construct(
        private AttendanceMonitoringService $monitoringService,
        private AttendanceAccessService $accessService
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

        $this->ensureCanViewStudent($request, (int) $validated['student_id']);

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
        if (! $this->accessService->isSupervisor($request->user())) {
            abort(403, 'Seule l\'administration peut déclencher les alertes globales.');
        }

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

        $alerts = $this->monitoringService->getActiveAlerts(
            $validated['semester_id'],
            $this->accessService->visibleStudentUserIds($request->user())
        );

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

        $this->ensureCanViewStudent($request, $studentId);

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

        $this->ensureCanViewStudent($request, $studentId);

        $stats = $this->monitoringService->calculateStudentStats(
            $studentId,
            $validated['semester_id']
        );

        return response()->json($stats);
    }

    private function ensureCanViewStudent(Request $request, int $studentId): void
    {
        if (! $this->accessService->canViewStudent($request->user(), $studentId)) {
            abort(403, 'Vous ne pouvez pas consulter le suivi de cet élève.');
        }
    }
}
