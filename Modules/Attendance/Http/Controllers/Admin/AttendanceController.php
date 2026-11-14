<?php

namespace Modules\Attendance\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Attendance\Entities\AttendanceRecord;
use Modules\Attendance\Entities\AttendanceSession;
use Modules\Attendance\Http\Requests\BulkRecordAttendanceRequest;
use Modules\Attendance\Http\Requests\ModifyAttendanceRecordRequest;
use Modules\Attendance\Http\Requests\StoreAttendanceSessionRequest;
use Modules\Attendance\Services\AttendanceAccessService;
use Modules\Attendance\Services\AttendanceService;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Semester;
use Modules\Timetable\Entities\TimetableSlot;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendanceService,
        private AttendanceAccessService $accessService
    ) {}

    /**
     * Créer session d'appel (Story 01).
     * Un Professeur ne peut faire l'appel que pour sa classe (professeur principal) ou ses créneaux.
     */
    public function createSession(StoreAttendanceSessionRequest $request): JsonResponse
    {
        if (! $this->accessService->sessionsHaveClasse()) {
            abort(503, 'Module présences en cours de mise à jour (migration requise). Réessayez plus tard.');
        }

        $validated = $request->validated();
        $user = $request->user();

        if (! empty($validated['classe_id'])) {
            $classe = Classe::query()->findOrFail($validated['classe_id']);
            if (! $this->accessService->canManageClasse($user, $classe)) {
                abort(403, 'Vous ne pouvez faire l\'appel que pour vos classes.');
            }
        } else {
            $slot = TimetableSlot::query()->findOrFail($validated['timetable_slot_id']);
            if (! $this->accessService->canManageTimetableSlot($user, $slot)) {
                abort(403, 'Vous ne pouvez faire l\'appel que pour vos créneaux.');
            }

            $validated['classe_id'] = $slot->getAttribute('classe_id');
            $validated['subject_id'] ??= $slot->getAttribute('subject_id');
            $validated['start_time'] ??= $slot->start_time ? substr((string) $slot->start_time, 0, 5) : null;
            $validated['end_time'] ??= $slot->end_time ? substr((string) $slot->end_time, 0, 5) : null;
        }

        $session = $this->attendanceService->createSession(
            $validated,
            Carbon::parse($validated['session_date']),
            $validated['method'] ?? 'manual',
            (int) $user->getAuthIdentifier()
        );

        return response()->json($session->load(['classe', 'subject', 'timetableSlot', 'records.student']), 201);
    }

    /**
     * Enregistrer présence/absence (Stories 01-02)
     */
    public function recordAttendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|integer|exists:tenant.attendance_sessions,id',
            'student_id' => 'required|integer|exists:tenant.users,id',
            'status' => ['required', 'string', Rule::in(['present', 'absent', 'late', 'excused'])],
            'arrival_time' => 'nullable|date_format:H:i',
        ]);

        $this->findManageableSession($request, (int) $validated['session_id']);

        $record = $this->attendanceService->recordAttendance(
            $validated['session_id'],
            $validated['student_id'],
            $validated['status'],
            $validated['arrival_time'] ?? null
        );

        return response()->json($record->load('student'));
    }

    /**
     * Saisie en lot (« Tout présent » / « Tout absent ») : un seul appel, une transaction.
     */
    public function recordAttendanceInBulk(BulkRecordAttendanceRequest $request, int $sessionId): JsonResponse
    {
        $session = $this->findManageableSession($request, $sessionId);
        $validated = $request->validated();

        $updatedCount = $this->attendanceService->recordAttendanceInBulk(
            $session,
            $validated['status'],
            isset($validated['student_ids']) ? array_map('intval', $validated['student_ids']) : null,
            (int) $request->user()->getAuthIdentifier()
        );

        return response()->json([
            'message' => 'Présences enregistrées',
            'updated_count' => $updatedCount,
            ...$this->attendanceService->getAttendanceSheet($session),
        ]);
    }

    /**
     * Modifier enregistrement (Story 03)
     */
    public function modifyRecord(ModifyAttendanceRecordRequest $request, int $recordId): JsonResponse
    {
        $validated = $request->validated();

        $record = AttendanceRecord::query()->findOrFail($recordId);
        $this->findManageableSession($request, (int) $record->attendance_session_id);

        $record = $this->attendanceService->modifyRecord(
            $record,
            $validated['status'],
            $validated['reason'],
            (int) $request->user()->getAuthIdentifier()
        );

        return response()->json($record->load(['student', 'modifier:id,firstname,lastname,username']));
    }

    /**
     * Feuille d'appel (Story 01)
     */
    public function getAttendanceSheet(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->findManageableSession($request, $sessionId);

        return response()->json($this->attendanceService->getAttendanceSheet($session));
    }

    /**
     * Compléter session
     */
    public function completeSession(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->findManageableSession($request, $sessionId);

        $session = $this->attendanceService->completeSession($session, (int) $request->user()->getAuthIdentifier());

        return response()->json([
            'message' => 'Session complétée avec succès',
            'session' => $session,
        ]);
    }

    /**
     * Enregistrement via QR code (Story 04)
     */
    public function recordViaQRCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|integer|exists:tenant.attendance_sessions,id',
            'student_id' => 'required|integer|exists:tenant.users,id',
            'qr_token' => 'required|string',
        ]);

        $this->findManageableSession($request, (int) $validated['session_id']);

        $success = $this->attendanceService->recordViaQRCode(
            $validated['session_id'],
            $validated['student_id'],
            $validated['qr_token']
        );

        return response()->json([
            'success' => $success,
            'message' => 'Présence enregistrée via QR code',
        ]);
    }

    /**
     * Liste sessions (un Professeur ne voit que les siennes).
     */
    public function index(Request $request): JsonResponse
    {
        $query = AttendanceSession::with(['timetableSlot.module', 'classe', 'subject', 'creator'])
            ->withCount([
                'records',
                'records as present_count' => fn ($q) => $q->where('status', 'present'),
                'records as absent_count' => fn ($q) => $q->where('status', 'absent'),
            ]);

        $user = $request->user();
        if (! $this->accessService->isSupervisor($user)) {
            $query->visibleToTeacher(
                (int) $user->getAuthIdentifier(),
                $this->accessService->sessionsHaveClasse() ? $this->accessService->teacherClasseIds($user) : []
            );
        }

        if ($request->filled('semester_id')) {
            $semester = Semester::query()->find($request->integer('semester_id'));
            if ($semester) {
                $query->withinSemester($semester);
            }
        }

        if ($request->filled('classe_id') && $this->accessService->sessionsHaveClasse()) {
            $query->where('classe_id', $request->integer('classe_id'));
        }

        if ($request->filled('session_date')) {
            $query->whereDate('session_date', $request->input('session_date'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sessions = $query->orderBy('session_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->paginate($request->integer('per_page', 15));

        return response()->json($sessions);
    }

    /**
     * Classes pour lesquelles l'utilisateur peut faire l'appel.
     */
    public function classes(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Classe::query()->withCount('activeEnrollments')->orderBy('name');

        if (! $this->accessService->isSupervisor($user)) {
            $query->whereIn('id', $this->accessService->teacherClasseIds($user));
        }

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->integer('academic_year_id'));
        }

        return response()->json([
            'data' => $query->get(['id', 'name', 'academic_year_id', 'head_teacher_id']),
        ]);
    }

    private function findManageableSession(Request $request, int $sessionId): AttendanceSession
    {
        $session = AttendanceSession::query()
            ->with(['classe', 'timetableSlot'])
            ->findOrFail($sessionId);

        if (! $this->accessService->canManageSession($request->user(), $session)) {
            abort(403, 'Vous n\'êtes pas autorisé à gérer cette séance d\'appel.');
        }

        return $session;
    }
}
