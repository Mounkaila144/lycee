<?php

namespace Modules\Attendance\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Entities\AttendanceRecord;
use Modules\Attendance\Entities\AttendanceSession;
use Modules\Enrollment\Entities\Student;

class AttendanceService
{
    /**
     * Créer une session d'appel (Story 01).
     *
     * @param  array{timetable_slot_id?: int|null, classe_id?: int|null, subject_id?: int|null, start_time?: string|null, end_time?: string|null, notes?: string|null}  $attributes
     */
    public function createSession(
        array $attributes,
        Carbon $sessionDate,
        string $method,
        int $userId
    ): AttendanceSession {
        return DB::connection('tenant')->transaction(function () use ($attributes, $sessionDate, $method, $userId) {
            $startTime = $attributes['start_time'] ?? null;
            $endTime = $attributes['end_time'] ?? null;

            $session = AttendanceSession::create([
                'timetable_slot_id' => $attributes['timetable_slot_id'] ?? null,
                'classe_id' => $attributes['classe_id'] ?? null,
                'subject_id' => $attributes['subject_id'] ?? null,
                'session_date' => $sessionDate->toDateString(),
                'start_time' => $startTime ?? now()->format('H:i:s'),
                'end_time' => $endTime ?? Carbon::parse($startTime ?? now())->addHours(2)->format('H:i:s'),
                'status' => 'draft',
                'method' => $method,
                'created_by' => $userId,
                'notes' => $attributes['notes'] ?? null,
            ]);

            $this->initializeSessionRecords($session, $userId);

            return $session;
        });
    }

    /**
     * Initialiser les enregistrements (absent par défaut) pour tous les élèves concernés.
     */
    private function initializeSessionRecords(AttendanceSession $session, int $userId): void
    {
        foreach ($this->studentUserIdsForSession($session) as $studentUserId) {
            AttendanceRecord::firstOrCreate(
                [
                    'attendance_session_id' => $session->id,
                    'student_id' => $studentUserId,
                ],
                [
                    'status' => 'absent',
                    'recorded_by' => $userId,
                ]
            );
        }
    }

    /**
     * Comptes utilisateurs (users.id) des élèves attendus à la séance.
     */
    private function studentUserIdsForSession(AttendanceSession $session): Collection
    {
        $students = Student::query()->whereNotNull('user_id');

        if ($session->classe_id) {
            $students->whereHas('classEnrollments', function ($query) use ($session) {
                $query->where('classe_id', $session->classe_id)->where('status', 'Actif');
            });
        } elseif ($session->timetableSlot?->group_id) {
            $studentIds = DB::connection('tenant')
                ->table('group_assignments')
                ->where('group_id', $session->timetableSlot->group_id)
                ->whereNull('deleted_at')
                ->pluck('student_id');

            $students->whereIn('id', $studentIds);
        } else {
            return collect();
        }

        return $students->pluck('user_id')->unique()->values();
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
            'delay_minutes' => 0,
        ];

        if ($status === 'late' && $arrivalTime) {
            $session = AttendanceSession::find($sessionId);
            $scheduledTime = Carbon::parse($session->start_time);
            $actualTime = Carbon::parse($arrivalTime);
            $data['delay_minutes'] = max(0, (int) $scheduledTime->diffInMinutes($actualTime, false));
        }

        $record->update($data);

        return $record;
    }

    /**
     * Modifier un enregistrement (Story 03)
     */
    public function modifyRecord(
        AttendanceRecord $record,
        string $newStatus,
        string $reason,
        int $userId
    ): AttendanceRecord {
        $record->update([
            'status' => $newStatus,
            'modified_by' => $userId,
            'modification_reason' => $reason,
        ]);

        return $record;
    }

    /**
     * Compléter une session
     */
    public function completeSession(AttendanceSession $session, int $userId): AttendanceSession
    {
        $session->update([
            'status' => 'completed',
            'completed_by' => $userId,
            'completed_at' => now(),
        ]);

        return $session;
    }

    /**
     * Importer appel via QR code (Story 04)
     */
    public function recordViaQRCode(int $sessionId, int $studentId, string $qrToken): bool
    {
        $this->recordAttendance($sessionId, $studentId, 'present', now()->format('H:i'));

        return true;
    }

    /**
     * Obtenir feuille d'appel
     *
     * @return array{session: AttendanceSession, records: Collection, summary: array<string, int>}
     */
    public function getAttendanceSheet(AttendanceSession $session): array
    {
        $session->load(['timetableSlot.module', 'timetableSlot.group', 'classe', 'subject', 'creator']);

        $records = AttendanceRecord::where('attendance_session_id', $session->id)
            ->with('student')
            ->orderBy('student_id')
            ->get();

        $profiles = Student::query()
            ->whereIn('user_id', $records->pluck('student_id'))
            ->get(['id', 'user_id', 'matricule', 'firstname', 'lastname'])
            ->keyBy('user_id');

        $records->each(function (AttendanceRecord $record) use ($profiles) {
            $profile = $profiles->get($record->student_id);
            $record->setAttribute('student_profile', $profile ? [
                'id' => $profile->id,
                'matricule' => $profile->matricule,
                'firstname' => $profile->firstname,
                'lastname' => $profile->lastname,
            ] : null);
        });

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
