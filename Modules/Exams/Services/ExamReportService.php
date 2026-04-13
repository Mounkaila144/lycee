<?php

namespace Modules\Exams\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Exams\Entities\ExamSession;

/**
 * Service for Exam Reports (Epic 4: Stories 11-13)
 * - Story 11: Generate exam attendance reports
 * - Story 12: Generate incident reports
 * - Story 13: Generate exam statistics and analytics
 */
class ExamReportService
{
    /**
     * Story 11: Generate attendance report for a session
     */
    public function generateAttendanceReport(ExamSession $session): array
    {
        $attendanceSheets = $session->attendanceSheets()
            ->with(['student', 'roomAssignment.room'])
            ->get();

        return [
            'session' => [
                'id' => $session->id,
                'title' => $session->title,
                'module' => $session->module->name,
                'date' => $session->exam_date,
                'time' => $session->start_time.' - '.$session->end_time,
            ],
            'summary' => [
                'total_registered' => $attendanceSheets->count(),
                'present' => $attendanceSheets->where('status', 'present')->count(),
                'absent' => $attendanceSheets->where('status', 'absent')->count(),
                'late' => $attendanceSheets->where('status', 'late')->count(),
                'excluded' => $attendanceSheets->where('status', 'excluded')->count(),
                'submitted' => $attendanceSheets->where('has_submitted', true)->count(),
            ],
            'by_room' => $attendanceSheets->groupBy('exam_room_assignment_id')->map(function ($sheets) {
                $room = $sheets->first()->roomAssignment;

                return [
                    'room_name' => $room->room->name,
                    'total' => $sheets->count(),
                    'present' => $sheets->where('status', 'present')->count(),
                    'absent' => $sheets->where('status', 'absent')->count(),
                ];
            })->values(),
            'students' => $attendanceSheets->map(function ($sheet) {
                return [
                    'student_id' => $sheet->student->id,
                    'student_name' => $sheet->student->full_name,
                    'room' => $sheet->roomAssignment?->room->name,
                    'seat' => $sheet->seat_number,
                    'status' => $sheet->status,
                    'arrival_time' => $sheet->arrival_time,
                    'submission_time' => $sheet->submission_time,
                    'has_submitted' => $sheet->has_submitted,
                ];
            }),
        ];
    }

    /**
     * Story 12: Generate incident report for a session
     */
    public function generateIncidentReport(ExamSession $session): array
    {
        $incidents = $session->incidents()
            ->with(['student', 'reporter', 'reviewer'])
            ->get();

        return [
            'session' => [
                'id' => $session->id,
                'title' => $session->title,
                'date' => $session->exam_date,
            ],
            'summary' => [
                'total_incidents' => $incidents->count(),
                'by_type' => $incidents->groupBy('type')->map->count(),
                'by_severity' => $incidents->groupBy('severity')->map->count(),
                'by_status' => $incidents->groupBy('status')->map->count(),
            ],
            'incidents' => $incidents->map(function ($incident) {
                return [
                    'id' => $incident->id,
                    'type' => $incident->type,
                    'severity' => $incident->severity,
                    'title' => $incident->title,
                    'description' => $incident->description,
                    'student' => $incident->student ? [
                        'id' => $incident->student->id,
                        'name' => $incident->student->full_name,
                    ] : null,
                    'occurred_at' => $incident->occurred_at_time,
                    'status' => $incident->status,
                    'reported_by' => $incident->reporter->name,
                    'action_taken' => $incident->action_taken,
                    'reviewed_by' => $incident->reviewer?->name,
                    'reviewed_at' => $incident->reviewed_at,
                ];
            }),
        ];
    }

    /**
     * Story 13: Generate exam statistics for a period
     */
    public function generateExamStatistics(?string $startDate = null, ?string $endDate = null, ?int $moduleId = null): array
    {
        $query = ExamSession::query()->with(['module', 'academicYear']);

        if ($startDate && $endDate) {
            $query->whereBetween('exam_date', [$startDate, $endDate]);
        }

        if ($moduleId) {
            $query->where('module_id', $moduleId);
        }

        $sessions = $query->get();

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'overview' => [
                'total_sessions' => $sessions->count(),
                'by_status' => $sessions->groupBy('status')->map->count(),
                'by_type' => $sessions->groupBy('type')->map->count(),
                'total_students_examined' => DB::connection('tenant')
                    ->table('exam_attendance_sheets')
                    ->whereIn('exam_session_id', $sessions->pluck('id'))
                    ->distinct('student_id')
                    ->count(),
            ],
            'attendance_stats' => [
                'total_attendance_records' => DB::connection('tenant')
                    ->table('exam_attendance_sheets')
                    ->whereIn('exam_session_id', $sessions->pluck('id'))
                    ->count(),
                'present' => DB::connection('tenant')
                    ->table('exam_attendance_sheets')
                    ->whereIn('exam_session_id', $sessions->pluck('id'))
                    ->where('status', 'present')
                    ->count(),
                'absent' => DB::connection('tenant')
                    ->table('exam_attendance_sheets')
                    ->whereIn('exam_session_id', $sessions->pluck('id'))
                    ->where('status', 'absent')
                    ->count(),
                'late' => DB::connection('tenant')
                    ->table('exam_attendance_sheets')
                    ->whereIn('exam_session_id', $sessions->pluck('id'))
                    ->where('status', 'late')
                    ->count(),
            ],
            'incident_stats' => [
                'total_incidents' => DB::connection('tenant')
                    ->table('exam_incidents')
                    ->whereIn('exam_session_id', $sessions->pluck('id'))
                    ->count(),
                'by_type' => DB::connection('tenant')
                    ->table('exam_incidents')
                    ->whereIn('exam_session_id', $sessions->pluck('id'))
                    ->select('type', DB::raw('count(*) as count'))
                    ->groupBy('type')
                    ->pluck('count', 'type'),
                'by_severity' => DB::connection('tenant')
                    ->table('exam_incidents')
                    ->whereIn('exam_session_id', $sessions->pluck('id'))
                    ->select('severity', DB::raw('count(*) as count'))
                    ->groupBy('severity')
                    ->pluck('count', 'severity'),
            ],
            'by_module' => $sessions->groupBy('module_id')->map(function ($moduleSessions) {
                $module = $moduleSessions->first()->module;

                return [
                    'module_id' => $module->id,
                    'module_name' => $module->name,
                    'total_sessions' => $moduleSessions->count(),
                    'total_students' => DB::connection('tenant')
                        ->table('exam_attendance_sheets')
                        ->whereIn('exam_session_id', $moduleSessions->pluck('id'))
                        ->distinct('student_id')
                        ->count(),
                ];
            })->values(),
        ];
    }

    /**
     * Story 11: Export attendance report to Excel/PDF
     */
    public function exportAttendanceReport(ExamSession $session, string $format = 'excel'): array
    {
        $data = $this->generateAttendanceReport($session);

        return [
            'filename' => 'attendance_report_'.$session->id.'_'.now()->format('Y-m-d'),
            'format' => $format,
            'data' => $data,
        ];
    }

    /**
     * Story 12: Export incident report
     */
    public function exportIncidentReport(ExamSession $session, string $format = 'pdf'): array
    {
        $data = $this->generateIncidentReport($session);

        return [
            'filename' => 'incident_report_'.$session->id.'_'.now()->format('Y-m-d'),
            'format' => $format,
            'data' => $data,
        ];
    }

    /**
     * Story 13: Get supervisor workload statistics
     */
    public function getSupervisorWorkloadStats(?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = DB::connection('tenant')
            ->table('exam_supervisors')
            ->join('teachers', 'exam_supervisors.teacher_id', '=', 'teachers.id')
            ->join('exam_sessions', 'exam_supervisors.exam_session_id', '=', 'exam_sessions.id')
            ->select(
                'teachers.id as teacher_id',
                'teachers.first_name',
                'teachers.last_name',
                DB::raw('COUNT(exam_supervisors.id) as total_supervisions'),
                DB::raw('SUM(CASE WHEN exam_supervisors.status = "present" THEN 1 ELSE 0 END) as completed'),
                DB::raw('SUM(exam_sessions.duration_minutes) as total_minutes')
            )
            ->groupBy('teachers.id', 'teachers.first_name', 'teachers.last_name');

        if ($startDate && $endDate) {
            $query->whereBetween('exam_sessions.exam_date', [$startDate, $endDate]);
        }

        return collect($query->get());
    }

    /**
     * Story 13: Get room utilization statistics
     */
    public function getRoomUtilizationStats(?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = DB::connection('tenant')
            ->table('exam_room_assignments')
            ->join('rooms', 'exam_room_assignments.room_id', '=', 'rooms.id')
            ->join('exam_sessions', 'exam_room_assignments.exam_session_id', '=', 'exam_sessions.id')
            ->select(
                'rooms.id as room_id',
                'rooms.name as room_name',
                'rooms.capacity as room_capacity',
                DB::raw('COUNT(exam_room_assignments.id) as times_used'),
                DB::raw('AVG(exam_room_assignments.assigned_students) as avg_students'),
                DB::raw('SUM(exam_room_assignments.assigned_students) as total_students')
            )
            ->groupBy('rooms.id', 'rooms.name', 'rooms.capacity');

        if ($startDate && $endDate) {
            $query->whereBetween('exam_sessions.exam_date', [$startDate, $endDate]);
        }

        return collect($query->get());
    }
}
