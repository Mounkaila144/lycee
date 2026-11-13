<?php

namespace Modules\Attendance\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Attendance\Entities\AttendanceAlert;
use Modules\Attendance\Entities\AttendanceRecord;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\Semester;

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
        $records = $this->recordsInSemester($semesterId)
            ->where('student_id', $studentId)
            ->get(['id', 'status']);

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
        $records = $this->recordsInSemester($semesterId)
            ->with(['session.timetableSlot.module', 'session.classe', 'session.subject'])
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

        $students = $this->studentUserIdsForSemester($semesterId);

        foreach ($students as $studentId) {
            $alert = $this->checkThresholdsForStudent($studentId, $semesterId);
            if ($alert) {
                $alerts->push($alert);
            }
        }

        return $alerts;
    }

    /**
     * Enregistrements de présence des séances tenues pendant le semestre.
     */
    public function recordsInSemester(int $semesterId): Builder
    {
        $semester = Semester::query()->findOrFail($semesterId);

        return AttendanceRecord::query()
            ->whereHas('session', fn (Builder $query) => $query->withinSemester($semester));
    }

    /**
     * Comptes utilisateurs (users.id) des élèves inscrits (actifs) sur l'année du semestre.
     *
     * @param  list<int>|null  $restrictTo
     */
    public function studentUserIdsForSemester(int $semesterId, ?array $restrictTo = null): Collection
    {
        $semester = Semester::query()->findOrFail($semesterId);

        return Student::query()
            ->whereNotNull('user_id')
            ->whereHas('classEnrollments', function (Builder $query) use ($semester) {
                $query->where('academic_year_id', $semester->academic_year_id)
                    ->where('status', 'Actif');
            })
            ->when($restrictTo !== null, fn (Builder $query) => $query->whereIn('user_id', $restrictTo))
            ->pluck('user_id')
            ->unique()
            ->values();
    }

    /**
     * Obtenir alertes actives
     *
     * @param  list<int>|null  $studentIds
     */
    public function getActiveAlerts(int $semesterId, ?array $studentIds = null): Collection
    {
        return AttendanceAlert::with(['student'])
            ->where('semester_id', $semesterId)
            ->when($studentIds !== null, fn ($query) => $query->whereIn('student_id', $studentIds))
            ->whereIn('status', ['pending', 'notified'])
            ->orderBy('absence_rate', 'desc')
            ->get();
    }
}
