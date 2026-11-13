<?php

namespace Modules\Attendance\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;

class AttendanceReportService
{
    public function __construct(private AttendanceMonitoringService $monitoringService) {}

    /**
     * Calculer taux d'assiduité (Story 11)
     *
     * @param  list<int>|null  $studentIds  restreint aux élèves visibles (null = tous)
     * @return array<string, int|float>
     */
    public function getAttendanceRates(int $semesterId, ?int $classeId = null, ?array $studentIds = null): array
    {
        $query = $this->scopedRecords($semesterId, $classeId, $studentIds);

        $counts = (clone $query)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $counts->sum();
        $present = (int) ($counts['present'] ?? 0);
        $absent = (int) ($counts['absent'] ?? 0);

        return [
            'total_sessions' => $total,
            'present_count' => $present,
            'absent_count' => $absent,
            'late_count' => (int) ($counts['late'] ?? 0),
            'excused_count' => (int) ($counts['excused'] ?? 0),
            'presence_rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
            'absence_rate' => $total > 0 ? round(($absent / $total) * 100, 2) : 0,
        ];
    }

    /**
     * @param  list<int>|null  $studentIds
     */
    private function scopedRecords(int $semesterId, ?int $classeId = null, ?array $studentIds = null): Builder
    {
        return $this->monitoringService->recordsInSemester($semesterId)
            ->when($classeId, fn (Builder $query) => $query->whereHas('session', fn (Builder $s) => $s->where('classe_id', $classeId)))
            ->when($studentIds !== null, fn (Builder $query) => $query->whereIn('student_id', $studentIds));
    }

    /**
     * Liste des absentéistes (Story 12)
     */
    /**
     * @param  list<int>|null  $studentIds
     */
    public function getAbsenteesList(int $semesterId, float $minAbsenceRate = 20.0, ?array $studentIds = null): Collection
    {
        $students = $this->monitoringService->studentUserIdsForSemester($semesterId, $studentIds);
        $users = User::query()->whereIn('id', $students)->get(['id', 'firstname', 'lastname', 'email'])->keyBy('id');
        $absentees = collect();

        foreach ($students as $studentId) {
            $stats = $this->monitoringService->calculateStudentStats($studentId, $semesterId);

            if ($stats['total_sessions'] > 0 && $stats['absence_rate'] >= $minAbsenceRate) {
                $student = $users->get($studentId);

                $absentees->push([
                    'student_id' => $studentId,
                    'student_name' => $student ? trim($student->firstname.' '.$student->lastname) : 'N/A',
                    'student_email' => $student->email ?? 'N/A',
                    'statistics' => $stats,
                ]);
            }
        }

        return $absentees->sortByDesc('statistics.absence_rate')->values();
    }

    /**
     * Statistiques détaillées de présences (Story 13)
     *
     * @param  list<int>|null  $studentIds
     * @return array<string, mixed>
     */
    public function getDetailedStatistics(int $semesterId, ?array $studentIds = null): array
    {
        $semester = Semester::query()->findOrFail($semesterId);

        $base = DB::connection('tenant')
            ->table('attendance_records')
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->whereNull('attendance_records.deleted_at')
            ->whereNull('attendance_sessions.deleted_at')
            ->whereBetween('attendance_sessions.session_date', [$semester->start_date->toDateString(), $semester->end_date->toDateString()])
            ->when($studentIds !== null, fn ($query) => $query->whereIn('attendance_records.student_id', $studentIds));

        $subjectStats = collect();
        $classeStats = collect();

        if (Schema::connection('tenant')->hasColumn('attendance_sessions', 'classe_id')) {
            // Stats par matière (séances lycée rattachées à une matière)
            $subjectStats = (clone $base)
                ->leftJoin('subjects', 'subjects.id', '=', 'attendance_sessions.subject_id')
                ->select([
                    'subjects.id as module_id',
                    DB::raw("COALESCE(subjects.name, 'Sans matière') as module_name"),
                    DB::raw('COUNT(*) as total_records'),
                    DB::raw("SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) as present_count"),
                    DB::raw("SUM(CASE WHEN attendance_records.status = 'absent' THEN 1 ELSE 0 END) as absent_count"),
                    DB::raw("SUM(CASE WHEN attendance_records.status = 'late' THEN 1 ELSE 0 END) as late_count"),
                ])
                ->groupBy('subjects.id', 'subjects.name')
                ->get();

            // Stats par classe
            $classeStats = (clone $base)
                ->leftJoin('classes', 'classes.id', '=', 'attendance_sessions.classe_id')
                ->select([
                    'classes.id as classe_id',
                    DB::raw("COALESCE(classes.name, 'Sans classe') as classe_name"),
                    DB::raw('COUNT(*) as total_records'),
                    DB::raw("SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) as present_count"),
                    DB::raw("SUM(CASE WHEN attendance_records.status = 'absent' THEN 1 ELSE 0 END) as absent_count"),
                ])
                ->groupBy('classes.id', 'classes.name')
                ->get();
        }

        // Stats par jour de la semaine (1 = dimanche … 7 = samedi, DAYOFWEEK MySQL)
        $dayStats = (clone $base)
            ->select([
                DB::raw('DAYOFWEEK(attendance_sessions.session_date) as day_of_week'),
                DB::raw('COUNT(*) as total_records'),
                DB::raw("SUM(CASE WHEN attendance_records.status = 'absent' THEN 1 ELSE 0 END) as absent_count"),
            ])
            ->groupBy(DB::raw('DAYOFWEEK(attendance_sessions.session_date)'))
            ->get();

        return [
            'global' => $this->getAttendanceRates($semesterId, null, $studentIds),
            'by_module' => $subjectStats,
            'by_classe' => $classeStats,
            'by_day' => $dayStats,
            'semester_id' => $semesterId,
        ];
    }

    /**
     * Export données pour Excel/PDF
     */
    /**
     * @param  list<int>|null  $studentIds
     */
    public function exportData(int $semesterId, string $type = 'students', ?array $studentIds = null): array
    {
        if ($type === 'students') {
            return $this->exportStudentData($semesterId, $studentIds);
        }

        if ($type === 'modules') {
            return $this->getDetailedStatistics($semesterId, $studentIds)['by_module']->toArray();
        }

        return [];
    }

    /**
     * @param  list<int>|null  $studentIds
     */
    private function exportStudentData(int $semesterId, ?array $studentIds = null): array
    {
        $studentUserIds = $this->monitoringService->studentUserIdsForSemester($semesterId, $studentIds);
        $users = User::query()->whereIn('id', $studentUserIds)->orderBy('lastname')->get(['id', 'firstname', 'lastname', 'email']);
        $data = [];

        foreach ($users as $user) {
            $data[] = array_merge([
                'student_id' => $user->id,
                'name' => trim($user->firstname.' '.$user->lastname),
                'email' => $user->email,
            ], $this->monitoringService->calculateStudentStats($user->id, $semesterId));
        }

        return $data;
    }
}
