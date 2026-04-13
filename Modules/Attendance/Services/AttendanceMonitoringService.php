<?php

namespace Modules\Attendance\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Entities\AttendanceAlert;
use Modules\Attendance\Entities\AttendanceRecord;

class AttendanceMonitoringService
{
    /**
     * Vérifier seuils et créer alertes (Story 08)
     */
    public function checkThresholdsForStudent(int $studentId, int $semesterId): ?AttendanceAlert
    {
        $stats = $this->calculateStudentStats($studentId, $semesterId);

        $warningThreshold = config('attendance.warning_threshold', 10);
        $criticalThreshold = config('attendance.critical_threshold', 20);

        $absenceRate = $stats['absence_rate'];

        if ($absenceRate >= $criticalThreshold) {
            return $this->createAlert($studentId, $semesterId, 'threshold_critical', $stats);
        }

        if ($absenceRate >= $warningThreshold) {
            return $this->createAlert($studentId, $semesterId, 'threshold_warning', $stats);
        }

        return null;
    }

    /**
     * Créer une alerte (Story 09)
     */
    private function createAlert(
        int $studentId,
        int $semesterId,
        string $alertType,
        array $stats
    ): AttendanceAlert {
        $threshold = $alertType === 'threshold_critical'
            ? config('attendance.critical_threshold', 20)
            : config('attendance.warning_threshold', 10);

        $message = sprintf(
            "Taux d'absence de %.2f%% (seuil: %d%%). Total: %d absences sur %d séances.",
            $stats['absence_rate'],
            $threshold,
            $stats['absent_count'],
            $stats['total_sessions']
        );

        return AttendanceAlert::create([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
            'alert_type' => $alertType,
            'absence_count' => $stats['absent_count'],
            'absence_rate' => $stats['absence_rate'],
            'threshold_value' => $threshold,
            'message' => $message,
            'status' => 'pending',
        ]);
    }

    /**
     * Calculer statistiques étudiant
     */
    public function calculateStudentStats(int $studentId, int $semesterId): array
    {
        $records = AttendanceRecord::whereHas('session', function ($query) use ($semesterId) {
            $query->whereHas('timetableSlot', function ($q) use ($semesterId) {
                $q->where('semester_id', $semesterId);
            });
        })
            ->where('student_id', $studentId)
            ->get();

        $total = $records->count();
        $absent = $records->where('status', 'absent')->count();
        $late = $records->where('status', 'late')->count();
        $present = $records->where('status', 'present')->count();
        $excused = $records->where('status', 'excused')->count();

        return [
            'total_sessions' => $total,
            'present_count' => $present,
            'absent_count' => $absent,
            'late_count' => $late,
            'excused_count' => $excused,
            'absence_rate' => $total > 0 ? round(($absent / $total) * 100, 2) : 0,
            'presence_rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Obtenir historique présences étudiant (Story 10)
     */
    public function getStudentHistory(int $studentId, int $semesterId): array
    {
        $records = AttendanceRecord::with(['session.timetableSlot.module'])
            ->whereHas('session', function ($query) use ($semesterId) {
                $query->whereHas('timetableSlot', function ($q) use ($semesterId) {
                    $q->where('semester_id', $semesterId);
                });
            })
            ->where('student_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = $this->calculateStudentStats($studentId, $semesterId);

        return [
            'records' => $records,
            'statistics' => $stats,
        ];
    }

    /**
     * Déclencher alertes automatiques pour tous les étudiants (Story 09)
     */
    public function triggerAutomaticAlerts(int $semesterId): Collection
    {
        $alerts = collect();

        $students = DB::connection('tenant')
            ->table('enrollments')
            ->where('semester_id', $semesterId)
            ->pluck('student_id')
            ->unique();

        foreach ($students as $studentId) {
            $alert = $this->checkThresholdsForStudent($studentId, $semesterId);
            if ($alert) {
                $alerts->push($alert);
            }
        }

        return $alerts;
    }

    /**
     * Obtenir alertes actives
     */
    public function getActiveAlerts(int $semesterId): Collection
    {
        return AttendanceAlert::with(['student'])
            ->where('semester_id', $semesterId)
            ->whereIn('status', ['pending', 'notified'])
            ->orderBy('absence_rate', 'desc')
            ->get();
    }
}
