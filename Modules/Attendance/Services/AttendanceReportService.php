<?php

namespace Modules\Attendance\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Entities\AttendanceRecord;

class AttendanceReportService
{
    /**
     * Calculer taux d'assiduité (Story 11)
     */
    public function getAttendanceRates(int $semesterId, ?int $groupId = null): array
    {
        $query = AttendanceRecord::whereHas('session', function ($q) use ($semesterId, $groupId) {
            $q->whereHas('timetableSlot', function ($tq) use ($semesterId, $groupId) {
                $tq->where('semester_id', $semesterId);
                if ($groupId) {
                    $tq->where('group_id', $groupId);
                }
            });
        });

        $total = $query->count();
        $present = (clone $query)->where('status', 'present')->count();
        $absent = (clone $query)->where('status', 'absent')->count();
        $late = (clone $query)->where('status', 'late')->count();
        $excused = (clone $query)->where('status', 'excused')->count();

        return [
            'total_sessions' => $total,
            'present_count' => $present,
            'absent_count' => $absent,
            'late_count' => $late,
            'excused_count' => $excused,
            'presence_rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
            'absence_rate' => $total > 0 ? round(($absent / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Liste des absentéistes (Story 12)
     */
    public function getAbsenteesList(int $semesterId, float $minAbsenceRate = 20.0): Collection
    {
        $students = DB::connection('tenant')
            ->table('enrollments')
            ->where('semester_id', $semesterId)
            ->pluck('student_id')
            ->unique();

        $monitoringService = app(AttendanceMonitoringService::class);
        $absentees = collect();

        foreach ($students as $studentId) {
            $stats = $monitoringService->calculateStudentStats($studentId, $semesterId);

            if ($stats['absence_rate'] >= $minAbsenceRate) {
                $student = DB::connection('tenant')
                    ->table('users')
                    ->where('id', $studentId)
                    ->first();

                $absentees->push([
                    'student_id' => $studentId,
                    'student_name' => $student->name ?? 'N/A',
                    'student_email' => $student->email ?? 'N/A',
                    'statistics' => $stats,
                ]);
            }
        }

        return $absentees->sortByDesc('statistics.absence_rate')->values();
    }

    /**
     * Statistiques détaillées de présences (Story 13)
     */
    public function getDetailedStatistics(int $semesterId): array
    {
        // Stats globales
        $globalStats = $this->getAttendanceRates($semesterId);

        // Stats par module
        $moduleStats = DB::connection('tenant')
            ->table('attendance_records')
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->join('timetable_slots', 'timetable_slots.id', '=', 'attendance_sessions.timetable_slot_id')
            ->join('modules', 'modules.id', '=', 'timetable_slots.module_id')
            ->where('timetable_slots.semester_id', $semesterId)
            ->select([
                'modules.id as module_id',
                'modules.name as module_name',
                DB::raw('COUNT(*) as total_records'),
                DB::raw("SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) as present_count"),
                DB::raw("SUM(CASE WHEN attendance_records.status = 'absent' THEN 1 ELSE 0 END) as absent_count"),
                DB::raw("SUM(CASE WHEN attendance_records.status = 'late' THEN 1 ELSE 0 END) as late_count"),
            ])
            ->groupBy('modules.id', 'modules.name')
            ->get();

        // Stats par jour de la semaine
        $dayStats = DB::connection('tenant')
            ->table('attendance_records')
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->join('timetable_slots', 'timetable_slots.id', '=', 'attendance_sessions.timetable_slot_id')
            ->where('timetable_slots.semester_id', $semesterId)
            ->select([
                'timetable_slots.day_of_week',
                DB::raw('COUNT(*) as total_records'),
                DB::raw("SUM(CASE WHEN attendance_records.status = 'absent' THEN 1 ELSE 0 END) as absent_count"),
            ])
            ->groupBy('timetable_slots.day_of_week')
            ->get();

        return [
            'global' => $globalStats,
            'by_module' => $moduleStats,
            'by_day' => $dayStats,
            'semester_id' => $semesterId,
        ];
    }

    /**
     * Export données pour Excel/PDF
     */
    public function exportData(int $semesterId, string $type = 'students'): array
    {
        if ($type === 'students') {
            return $this->exportStudentData($semesterId);
        }

        if ($type === 'modules') {
            return $this->exportModuleData($semesterId);
        }

        return [];
    }

    private function exportStudentData(int $semesterId): array
    {
        $students = DB::connection('tenant')
            ->table('enrollments')
            ->join('users', 'users.id', '=', 'enrollments.student_id')
            ->where('enrollments.semester_id', $semesterId)
            ->select('users.id', 'users.name', 'users.email')
            ->get();

        $monitoringService = app(AttendanceMonitoringService::class);
        $data = [];

        foreach ($students as $student) {
            $stats = $monitoringService->calculateStudentStats($student->id, $semesterId);
            $data[] = array_merge([
                'student_id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
            ], $stats);
        }

        return $data;
    }

    private function exportModuleData(int $semesterId): array
    {
        return DB::connection('tenant')
            ->table('attendance_records')
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->join('timetable_slots', 'timetable_slots.id', '=', 'attendance_sessions.timetable_slot_id')
            ->join('modules', 'modules.id', '=', 'timetable_slots.module_id')
            ->where('timetable_slots.semester_id', $semesterId)
            ->select([
                'modules.code',
                'modules.name',
                DB::raw('COUNT(DISTINCT attendance_sessions.id) as session_count'),
                DB::raw('COUNT(*) as total_records'),
                DB::raw("SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) as present_count"),
                DB::raw("SUM(CASE WHEN attendance_records.status = 'absent' THEN 1 ELSE 0 END) as absent_count"),
            ])
            ->groupBy('modules.id', 'modules.code', 'modules.name')
            ->get()
            ->toArray();
    }
}
