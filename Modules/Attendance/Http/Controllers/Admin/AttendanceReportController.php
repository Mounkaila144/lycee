<?php

namespace Modules\Attendance\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Attendance\Http\Requests\AttendanceReportRequest;
use Modules\Attendance\Services\AttendanceAccessService;
use Modules\Attendance\Services\AttendanceReportService;

class AttendanceReportController extends Controller
{
    public function __construct(
        private AttendanceReportService $reportService,
        private AttendanceAccessService $accessService
    ) {}

    /**
     * Taux d'assiduité (Story 11)
     */
    public function getAttendanceRates(AttendanceReportRequest $request): JsonResponse
    {
        $this->ensureCanFilterClasse($request);

        $rates = $this->reportService->getAttendanceRates(
            $request->integer('semester_id'),
            $request->sessionFilters(),
            $this->accessService->visibleStudentUserIds($request->user())
        );

        return response()->json($rates);
    }

    /**
     * Liste absentéistes (Story 12)
     */
    public function getAbsenteesList(AttendanceReportRequest $request): JsonResponse
    {
        $this->ensureCanFilterClasse($request);

        $absentees = $this->reportService->getAbsenteesList(
            $request->integer('semester_id'),
            (float) ($request->validated('min_absence_rate') ?? config('attendance.default_absence_rate_threshold', 20.0)),
            $this->accessService->visibleStudentUserIds($request->user()),
            $request->sessionFilters()
        );

        return response()->json($absentees);
    }

    /**
     * Statistiques détaillées (Story 13)
     */
    public function getDetailedStatistics(AttendanceReportRequest $request): JsonResponse
    {
        $this->ensureCanFilterClasse($request);

        $statistics = $this->reportService->getDetailedStatistics(
            $request->integer('semester_id'),
            $this->accessService->visibleStudentUserIds($request->user()),
            $request->sessionFilters()
        );

        return response()->json($statistics);
    }

    /**
     * Export données
     */
    public function exportData(AttendanceReportRequest $request): JsonResponse
    {
        $this->ensureCanFilterClasse($request);

        $data = $this->reportService->exportData(
            $request->integer('semester_id'),
            (string) $request->validated('type'),
            $this->accessService->visibleStudentUserIds($request->user()),
            $request->sessionFilters()
        );

        return response()->json([
            'data' => $data,
            'export_type' => $request->validated('type'),
            'semester_id' => $request->integer('semester_id'),
        ]);
    }

    /**
     * Un Professeur ne peut filtrer que sur l'une de ses classes.
     */
    private function ensureCanFilterClasse(AttendanceReportRequest $request): void
    {
        $classeId = $request->sessionFilters()['classe_id'];
        $user = $request->user();

        if ($classeId !== null
            && ! $this->accessService->isSupervisor($user)
            && ! in_array($classeId, $this->accessService->teacherClasseIds($user), true)) {
            abort(403, 'Vous ne pouvez consulter que les présences de vos classes.');
        }
    }
}
