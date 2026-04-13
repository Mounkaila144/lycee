<?php

namespace Modules\Attendance\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Attendance\Entities\AttendanceSession;
use Modules\Attendance\Services\AttendanceService;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendanceService
    ) {}

    /**
     * Créer session d'appel (Story 01)
     */
    public function createSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'timetable_slot_id' => 'required|integer|exists:tenant.timetable_slots,id',
            'session_date' => 'required|date',
            'method' => ['nullable', 'string', Rule::in(['manual', 'mobile', 'qr_code', 'imported'])],
        ]);

        $session = $this->attendanceService->createSession(
            $validated['timetable_slot_id'],
            Carbon::parse($validated['session_date']),
            $validated['method'] ?? 'manual'
        );

        return response()->json($session->load(['timetableSlot', 'records.student']), 201);
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

        $record = $this->attendanceService->recordAttendance(
            $validated['session_id'],
            $validated['student_id'],
            $validated['status'],
            $validated['arrival_time'] ?? null
        );

        return response()->json($record->load('student'));
    }

    /**
     * Modifier enregistrement (Story 03)
     */
    public function modifyRecord(Request $request, int $recordId): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['present', 'absent', 'late', 'excused'])],
            'reason' => 'required|string|max:500',
        ]);

        $record = $this->attendanceService->modifyRecord(
            $recordId,
            $validated['status'],
            $validated['reason']
        );

        return response()->json($record->load('student'));
    }

    /**
     * Feuille d'appel (Story 01)
     */
    public function getAttendanceSheet(int $sessionId): JsonResponse
    {
        $sheet = $this->attendanceService->getAttendanceSheet($sessionId);

        return response()->json($sheet);
    }

    /**
     * Compléter session
     */
    public function completeSession(int $sessionId): JsonResponse
    {
        $session = $this->attendanceService->completeSession($sessionId);

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
     * Liste sessions
     */
    public function index(Request $request): JsonResponse
    {
        $query = AttendanceSession::with(['timetableSlot.module', 'creator']);

        if ($request->has('semester_id')) {
            $query->whereHas('timetableSlot', fn ($q) => $q->where('semester_id', $request->semester_id));
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $sessions = $query->orderBy('session_date', 'desc')->paginate(15);

        return response()->json($sessions);
    }
}
