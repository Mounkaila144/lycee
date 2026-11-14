<?php

namespace Modules\Attendance\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;

/**
 * Rapports de présences. Les filtres de séances acceptés partout sont :
 * classe (classe_id) et période (date_from / date_to), en plus du semestre.
 *
 * @phpstan-type SessionFilters array{classe_id?: int|null, date_from?: string|null, date_to?: string|null}
 */
class AttendanceReportService
{
    private ?bool $sessionsHaveClasse = null;

    public function __construct(private AttendanceMonitoringService $monitoringService) {}

    /**
     * Calculer taux d'assiduité (Story 11)
     *
     * @param  array{classe_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     * @param  list<int>|null  $studentIds  restreint aux élèves visibles (null = tous)
     * @return array<string, int|float>
     */
    public function getAttendanceRates(int $semesterId, array $filters = [], ?array $studentIds = null): array
    {
        $counts = $this->scopedRecords($semesterId, $filters, $studentIds)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return $this->buildStats(
            (int) $counts->sum(),
            (int) ($counts['present'] ?? 0),
            (int) ($counts['absent'] ?? 0),
            (int) ($counts['late'] ?? 0),
            (int) ($counts['excused'] ?? 0),
        );
    }

    /**
     * Liste des absentéistes (Story 12)
     *
     * @param  array{classe_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     * @param  list<int>|null  $studentIds
     */
    public function getAbsenteesList(int $semesterId, float $minAbsenceRate = 20.0, ?array $studentIds = null, array $filters = []): Collection
    {
        $students = $this->monitoringService->studentUserIdsForSemester($semesterId, $studentIds)->all();
        $statsByStudent = $this->statsByStudent($semesterId, $filters, $students);
        $users = User::query()->whereIn('id', $statsByStudent->keys())->get(['id', 'firstname', 'lastname', 'email'])->keyBy('id');

        return $statsByStudent
            ->filter(fn (array $stats) => $stats['total_sessions'] > 0 && $stats['absence_rate'] >= $minAbsenceRate)
            ->map(function (array $stats, int $studentId) use ($users) {
                $student = $users->get($studentId);

                return [
                    'student_id' => $studentId,
                    'student_name' => $student ? trim($student->firstname.' '.$student->lastname) : 'N/A',
                    'student_email' => $student->email ?? 'N/A',
                    'statistics' => $stats,
                ];
            })
            ->sortByDesc('statistics.absence_rate')
            ->values();
    }

    /**
     * Statistiques détaillées de présences (Story 13)
     *
     * @param  array{classe_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     * @param  list<int>|null  $studentIds
     * @return array<string, mixed>
     */
    public function getDetailedStatistics(int $semesterId, ?array $studentIds = null, array $filters = []): array
    {
        $semester = Semester::query()->findOrFail($semesterId);

        $base = DB::connection('tenant')
            ->table('attendance_records')
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->whereNull('attendance_records.deleted_at')
            ->whereNull('attendance_sessions.deleted_at')
            ->whereBetween('attendance_sessions.session_date', [$semester->start_date->toDateString(), $semester->end_date->toDateString()])
            ->when($studentIds !== null, fn (QueryBuilder $query) => $query->whereIn('attendance_records.student_id', $studentIds));

        $this->applySessionFilters($base, $filters, 'attendance_sessions.');

        $subjectStats = collect();
        $classeStats = collect();

        if ($this->sessionsHaveClasse()) {
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
            ->orderBy('day_of_week')
            ->get();

        return [
            'global' => $this->getAttendanceRates($semesterId, $filters, $studentIds),
            'by_module' => $subjectStats,
            'by_classe' => $classeStats,
            'by_day' => $dayStats,
            'semester_id' => $semesterId,
        ];
    }

    /**
     * Export données pour Excel/PDF
     *
     * @param  array{classe_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     * @param  list<int>|null  $studentIds
     * @return array<int, mixed>
     */
    public function exportData(int $semesterId, string $type = 'students', ?array $studentIds = null, array $filters = []): array
    {
        if ($type === 'students') {
            return $this->exportStudentData($semesterId, $studentIds, $filters);
        }

        if ($type === 'modules') {
            return $this->getDetailedStatistics($semesterId, $studentIds, $filters)['by_module']->toArray();
        }

        return [];
    }

    /**
     * @param  array{classe_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     * @param  list<int>|null  $studentIds
     * @return list<array<string, mixed>>
     */
    private function exportStudentData(int $semesterId, ?array $studentIds = null, array $filters = []): array
    {
        $studentUserIds = $this->monitoringService->studentUserIdsForSemester($semesterId, $studentIds)->all();

        if (! empty($filters['classe_id'])) {
            $studentUserIds = array_values(array_intersect(
                $studentUserIds,
                $this->monitoringService->studentUserIdsForClasse((int) $filters['classe_id'])
            ));
        }

        $statsByStudent = $this->statsByStudent($semesterId, $filters, $studentUserIds);
        $users = User::query()->whereIn('id', $studentUserIds)->orderBy('lastname')->orderBy('firstname')->get(['id', 'firstname', 'lastname', 'email']);
        $data = [];

        foreach ($users as $user) {
            $data[] = array_merge([
                'student_id' => $user->id,
                'name' => trim($user->firstname.' '.$user->lastname),
                'email' => $user->email,
            ], $statsByStudent->get($user->id, $this->buildStats(0, 0, 0, 0, 0)));
        }

        return $data;
    }

    /**
     * Statistiques de présence agrégées par élève (users.id), en une seule requête.
     *
     * @param  array{classe_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     * @param  list<int>  $studentIds
     * @return Collection<int, array<string, int|float>>
     */
    private function statsByStudent(int $semesterId, array $filters, array $studentIds): Collection
    {
        if ($studentIds === []) {
            return collect();
        }

        return $this->scopedRecords($semesterId, $filters, $studentIds)
            ->toBase()
            ->select([
                'student_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count"),
                DB::raw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count"),
                DB::raw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count"),
                DB::raw("SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_count"),
            ])
            ->groupBy('student_id')
            ->get()
            ->mapWithKeys(fn (object $row) => [(int) $row->student_id => $this->buildStats(
                (int) $row->total,
                (int) $row->present_count,
                (int) $row->absent_count,
                (int) $row->late_count,
                (int) $row->excused_count,
            )]);
    }

    /**
     * @param  array{classe_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     * @param  list<int>|null  $studentIds
     */
    private function scopedRecords(int $semesterId, array $filters = [], ?array $studentIds = null): Builder
    {
        return $this->monitoringService->recordsInSemester($semesterId)
            ->whereHas('session', fn (Builder $session) => $this->applySessionFilters($session, $filters))
            ->when($studentIds !== null, fn (Builder $query) => $query->whereIn('student_id', $studentIds));
    }

    /**
     * @param  array{classe_id?: int|null, date_from?: string|null, date_to?: string|null}  $filters
     */
    private function applySessionFilters(Builder|QueryBuilder $query, array $filters, string $prefix = ''): Builder|QueryBuilder
    {
        if (! empty($filters['classe_id']) && $this->sessionsHaveClasse()) {
            $query->where($prefix.'classe_id', (int) $filters['classe_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate($prefix.'session_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate($prefix.'session_date', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * @return array<string, int|float>
     */
    private function buildStats(int $total, int $present, int $absent, int $late, int $excused): array
    {
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

    private function sessionsHaveClasse(): bool
    {
        return $this->sessionsHaveClasse ??= Schema::connection('tenant')->hasColumn('attendance_sessions', 'classe_id');
    }
}
