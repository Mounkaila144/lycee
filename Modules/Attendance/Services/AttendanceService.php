<?php

namespace Modules\Attendance\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Entities\AttendanceRecord;
use Modules\Attendance\Entities\AttendanceSession;

class AttendanceService
{
    /**
     * Créer une session d'appel (Story 01)
     */
    public function createSession(
        int $timetableSlotId,
        Carbon $sessionDate,
        string $method = 'manual'
    ): AttendanceSession {
        return DB::connection('tenant')->transaction(function () use ($timetableSlotId, $sessionDate, $method) {
            $session = AttendanceSession::create([
                'timetable_slot_id' => $timetableSlotId,
                'session_date' => $sessionDate,
                'start_time' => now()->format('H:i:s'),
                'end_time' => now()->addHours(2)->format('H:i:s'),
                'status' => 'draft',
                'method' => $method,
                'created_by' => auth()->id(),
            ]);

            // Initialiser records pour tous les étudiants du groupe
            $this->initializeSessionRecords($session);

            return $session;
        });
    }

    /**
     * Initialiser les enregistrements pour tous les étudiants
     */
    private function initializeSessionRecords(AttendanceSession $session): void
    {
        $slot = $session->timetableSlot;
        $groupId = $slot->group_id;

        $students = DB::connection('tenant')
            ->table('group_student')
            ->where('group_id', $groupId)
            ->pluck('student_id');

        foreach ($students as $studentId) {
            AttendanceRecord::create([
                'attendance_session_id' => $session->id,
                'student_id' => $studentId,
                'status' => 'absent',
                'recorded_by' => auth()->id(),
            ]);
        }
    }

    /**
     * Marquer présence/absence (Stories 01-02)
     */
    public function recordAttendance(
        int $sessionId,
        int $studentId,
        string $status,
        ?string $arrivalTime = null
    ): AttendanceRecord {
        $record = AttendanceRecord::where('attendance_session_id', $sessionId)
            ->where('student_id', $studentId)
            ->firstOrFail();

        $data = [
            'status' => $status,
            'arrival_time' => $arrivalTime,
        ];

        // Calculer retard si late
        if ($status === 'late' && $arrivalTime) {
            $session = AttendanceSession::find($sessionId);
            $scheduledTime = Carbon::parse($session->start_time);
            $actualTime = Carbon::parse($arrivalTime);
            $data['delay_minutes'] = $scheduledTime->diffInMinutes($actualTime);
        }

        $record->update($data);

        return $record;
    }

    /**
     * Modifier un enregistrement (Story 03)
     */
    public function modifyRecord(
        int $recordId,
        string $newStatus,
        string $reason
    ): AttendanceRecord {
        $record = AttendanceRecord::findOrFail($recordId);

        $record->update([
            'status' => $newStatus,
            'modified_by' => auth()->id(),
            'modification_reason' => $reason,
        ]);

        return $record;
    }

    /**
     * Compléter une session
     */
    public function completeSession(int $sessionId): AttendanceSession
    {
        $session = AttendanceSession::findOrFail($sessionId);

        $session->update([
            'status' => 'completed',
            'completed_by' => auth()->id(),
            'completed_at' => now(),
        ]);

        return $session;
    }

    /**
     * Importer appel via QR code (Story 04)
     */
    public function recordViaQRCode(int $sessionId, int $studentId, string $qrToken): bool
    {
        // Vérifier validité du token QR
        // Pour MVP, enregistrer directement
        $this->recordAttendance($sessionId, $studentId, 'present', now()->format('H:i:s'));

        return true;
    }

    /**
     * Obtenir feuille d'appel
     */
    public function getAttendanceSheet(int $sessionId): array
    {
        $session = AttendanceSession::with(['timetableSlot.module', 'timetableSlot.group'])
            ->findOrFail($sessionId);

        $records = AttendanceRecord::where('attendance_session_id', $sessionId)
            ->with('student')
            ->orderBy('student_id')
            ->get();

        return [
            'session' => $session,
            'records' => $records,
            'summary' => [
                'total' => $records->count(),
                'present' => $records->where('status', 'present')->count(),
                'absent' => $records->where('status', 'absent')->count(),
                'late' => $records->where('status', 'late')->count(),
                'excused' => $records->where('status', 'excused')->count(),
            ],
        ];
    }
}
