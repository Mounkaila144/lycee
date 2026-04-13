<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Entities\SemesterResult;

class SuccessStatisticsService
{
    /**
     * Get global statistics for a semester
     */
    public function getGlobalStatistics(int $semesterId, ?int $programmeId = null): array
    {
        $query = SemesterResult::query()
            ->where('semester_id', $semesterId);

        if ($programmeId) {
            $query->whereHas('student', fn ($q) => $q->where('programme_id', $programmeId));
        }

        $results = $query->get();
        $total = $results->count();

        if ($total === 0) {
            return $this->emptyStatistics();
        }

        $admitted = $results->where('final_status', 'admitted')->count();
        $admittedWithDebts = $results->where('final_status', 'admitted_with_debts')->count();
        $deferredFinal = $results->where('final_status', 'deferred_final')->count();
        $repeating = $results->where('final_status', 'repeating')->count();

        $toRetake = $results->where('global_status', 'to_retake')->count();
        $compensated = $results->where('compensated_modules_count', '>', 0)->count();

        $withRetakeSession = $results->where('retake_session_completed', true)->count();
        $improvedByRetake = $results->filter(function ($r) {
            return $r->retake_session_completed &&
                   in_array($r->final_status, ['admitted', 'admitted_with_debts']);
        })->count();

        return [
            'total_students' => $total,
            'rates' => [
                'success_rate' => $this->percentage($admitted + $admittedWithDebts, $total),
                'admission_rate' => $this->percentage($admitted, $total),
                'admitted_with_debts_rate' => $this->percentage($admittedWithDebts, $total),
                'compensation_rate' => $this->percentage($compensated, $total),
                'retake_rate' => $this->percentage($toRetake, $total),
                'failure_rate' => $this->percentage($deferredFinal + $repeating, $total),
            ],
            'counts' => [
                'admitted' => $admitted,
                'admitted_with_debts' => $admittedWithDebts,
                'deferred_final' => $deferredFinal,
                'repeating' => $repeating,
                'to_retake' => $toRetake,
                'with_compensation' => $compensated,
            ],
            'averages' => [
                'class_average' => $results->avg('average') ? round($results->avg('average'), 2) : null,
                'average_ects' => round($results->avg('acquired_credits'), 2),
                'average_success_rate' => round($results->avg('success_rate'), 2),
            ],
            'retake_impact' => [
                'students_with_retake' => $withRetakeSession,
                'improved_by_retake' => $improvedByRetake,
                'retake_success_rate' => $this->percentage($improvedByRetake, $withRetakeSession),
            ],
        ];
    }

    /**
     * Get statistics by module
     */
    public function getModuleStatistics(int $semesterId, ?int $programmeId = null): Collection
    {
        $query = ModuleGrade::query()
            ->where('semester_id', $semesterId)
            ->with(['module', 'module.teachers']);

        if ($programmeId) {
            $query->whereHas('student', fn ($q) => $q->where('programme_id', $programmeId));
        }

        $moduleGrades = $query->get()
            ->groupBy('module_id');

        return $moduleGrades->map(function ($grades, $moduleId) {
            $module = $grades->first()?->module;
            $total = $grades->count();
            $passed = $grades->where('average', '>=', 10)->count();
            $failed = $grades->filter(fn ($g) => $g->average !== null && $g->average < 10)->count();
            $absent = $grades->whereNull('average')->count();
            $compensated = $grades->where('status', 'Compensated')->count();

            $averages = $grades->pluck('average')->filter();

            return [
                'module_id' => $moduleId,
                'module' => $module ? [
                    'code' => $module->code,
                    'name' => $module->name,
                    'credits_ects' => $module->credits_ects,
                    'is_eliminatory' => $module->is_eliminatory,
                ] : null,
                'teacher' => $module?->teachers?->first() ? [
                    'id' => $module->teachers->first()->id,
                    'name' => $module->teachers->first()->full_name,
                ] : null,
                'total_students' => $total,
                'passed' => $passed,
                'failed' => $failed,
                'absent' => $absent,
                'compensated' => $compensated,
                'class_average' => $averages->count() > 0 ? round($averages->avg(), 2) : null,
                'success_rate' => $this->percentage($passed + $compensated, $total),
                'failure_rate' => $this->percentage($failed, $total),
                'min_grade' => $averages->count() > 0 ? round($averages->min(), 2) : null,
                'max_grade' => $averages->count() > 0 ? round($averages->max(), 2) : null,
                'std_deviation' => $this->standardDeviation($averages->toArray()),
                'status_indicator' => $this->getStatusIndicator($this->percentage($passed + $compensated, $total)),
            ];
        })
            ->sortBy('success_rate')
            ->values();
    }

    /**
     * Get statistics by programme/filiere
     */
    public function getProgrammeStatistics(int $semesterId): Collection
    {
        return DB::connection('tenant')
            ->table('semester_results')
            ->join('students', 'semester_results.student_id', '=', 'students.id')
            ->join('programmes', 'students.programme_id', '=', 'programmes.id')
            ->where('semester_results.semester_id', $semesterId)
            ->select(
                'programmes.id as programme_id',
                'programmes.name as programme_name',
                'programmes.code as programme_code',
                DB::raw('COUNT(*) as total'),
                DB::raw('AVG(semester_results.average) as avg_average'),
                DB::raw('SUM(CASE WHEN semester_results.final_status IN ("admitted", "admitted_with_debts") THEN 1 ELSE 0 END) as admitted'),
                DB::raw('SUM(CASE WHEN semester_results.final_status = "admitted" THEN 1 ELSE 0 END) as fully_admitted'),
                DB::raw('SUM(CASE WHEN semester_results.final_status = "deferred_final" THEN 1 ELSE 0 END) as deferred'),
                DB::raw('AVG(semester_results.acquired_credits) as avg_credits'),
                DB::raw('AVG(semester_results.total_credits) as total_credits')
            )
            ->groupBy('programmes.id', 'programmes.name', 'programmes.code')
            ->get()
            ->map(function ($row) {
                return [
                    'programme_id' => $row->programme_id,
                    'programme_name' => $row->programme_name,
                    'programme_code' => $row->programme_code,
                    'total_students' => $row->total,
                    'class_average' => $row->avg_average ? round($row->avg_average, 2) : null,
                    'success_rate' => $row->total > 0 ? round(($row->admitted / $row->total) * 100, 2) : 0,
                    'full_admission_rate' => $row->total > 0 ? round(($row->fully_admitted / $row->total) * 100, 2) : 0,
                    'failure_rate' => $row->total > 0 ? round(($row->deferred / $row->total) * 100, 2) : 0,
                    'avg_credits_acquired' => round($row->avg_credits, 2),
                    'avg_credits_total' => round($row->total_credits, 2),
                    'credits_completion_rate' => $row->total_credits > 0
                        ? round(($row->avg_credits / $row->total_credits) * 100, 2)
                        : 0,
                    'status_indicator' => $this->getStatusIndicator(
                        $row->total > 0 ? round(($row->admitted / $row->total) * 100, 2) : 0
                    ),
                ];
            })
            ->sortByDesc('success_rate')
            ->values();
    }

    /**
     * Get grade distribution
     */
    public function getDistribution(int $semesterId, ?int $programmeId = null): array
    {
        $query = SemesterResult::query()
            ->where('semester_id', $semesterId);

        if ($programmeId) {
            $query->whereHas('student', fn ($q) => $q->where('programme_id', $programmeId));
        }

        $averages = $query->pluck('average')->filter();

        $distribution = [
            '0-5' => 0,
            '5-8' => 0,
            '8-10' => 0,
            '10-12' => 0,
            '12-14' => 0,
            '14-16' => 0,
            '16-20' => 0,
        ];

        foreach ($averages as $avg) {
            if ($avg < 5) {
                $distribution['0-5']++;
            } elseif ($avg < 8) {
                $distribution['5-8']++;
            } elseif ($avg < 10) {
                $distribution['8-10']++;
            } elseif ($avg < 12) {
                $distribution['10-12']++;
            } elseif ($avg < 14) {
                $distribution['12-14']++;
            } elseif ($avg < 16) {
                $distribution['14-16']++;
            } else {
                $distribution['16-20']++;
            }
        }

        $stats = [
            'mean' => $averages->count() > 0 ? round($averages->avg(), 2) : null,
            'median' => $averages->count() > 0 ? round($averages->median(), 2) : null,
            'std_deviation' => $this->standardDeviation($averages->toArray()),
            'min' => $averages->count() > 0 ? round($averages->min(), 2) : null,
            'max' => $averages->count() > 0 ? round($averages->max(), 2) : null,
            'count' => $averages->count(),
        ];

        return [
            'distribution' => $distribution,
            'statistics' => $stats,
            'percentages' => collect($distribution)->map(function ($count) use ($averages) {
                return $averages->count() > 0 ? round(($count / $averages->count()) * 100, 2) : 0;
            })->toArray(),
        ];
    }

    /**
     * Get semester comparison (evolution over time)
     */
    public function getSemesterComparison(int $academicYearId): Collection
    {
        return DB::connection('tenant')
            ->table('semester_results')
            ->join('semesters', 'semester_results.semester_id', '=', 'semesters.id')
            ->where('semesters.academic_year_id', $academicYearId)
            ->select(
                'semesters.id as semester_id',
                'semesters.name as semester_name',
                'semesters.order as semester_order',
                DB::raw('COUNT(*) as total'),
                DB::raw('AVG(semester_results.average) as avg_average'),
                DB::raw('SUM(CASE WHEN semester_results.final_status IN ("admitted", "admitted_with_debts") THEN 1 ELSE 0 END) as admitted'),
                DB::raw('AVG(semester_results.acquired_credits) as avg_credits')
            )
            ->groupBy('semesters.id', 'semesters.name', 'semesters.order')
            ->orderBy('semesters.order')
            ->get()
            ->map(function ($row) {
                return [
                    'semester_id' => $row->semester_id,
                    'semester_name' => $row->semester_name,
                    'total_students' => $row->total,
                    'class_average' => $row->avg_average ? round($row->avg_average, 2) : null,
                    'success_rate' => $row->total > 0 ? round(($row->admitted / $row->total) * 100, 2) : 0,
                    'avg_credits' => round($row->avg_credits, 2),
                ];
            });
    }

    /**
     * Get historical comparison across academic years
     */
    public function getHistoricalComparison(int $programmeId, int $semesterOrder): Collection
    {
        return DB::connection('tenant')
            ->table('semester_results')
            ->join('semesters', 'semester_results.semester_id', '=', 'semesters.id')
            ->join('academic_years', 'semesters.academic_year_id', '=', 'academic_years.id')
            ->join('students', 'semester_results.student_id', '=', 'students.id')
            ->where('students.programme_id', $programmeId)
            ->where('semesters.order', $semesterOrder)
            ->select(
                'academic_years.id as academic_year_id',
                'academic_years.name as academic_year_name',
                DB::raw('COUNT(*) as total'),
                DB::raw('AVG(semester_results.average) as avg_average'),
                DB::raw('SUM(CASE WHEN semester_results.final_status IN ("admitted", "admitted_with_debts") THEN 1 ELSE 0 END) as admitted')
            )
            ->groupBy('academic_years.id', 'academic_years.name')
            ->orderBy('academic_years.id')
            ->get()
            ->map(function ($row) {
                return [
                    'academic_year_id' => $row->academic_year_id,
                    'academic_year_name' => $row->academic_year_name,
                    'total_students' => $row->total,
                    'class_average' => $row->avg_average ? round($row->avg_average, 2) : null,
                    'success_rate' => $row->total > 0 ? round(($row->admitted / $row->total) * 100, 2) : 0,
                ];
            });
    }

    /**
     * Get top performers
     */
    public function getTopPerformers(int $semesterId, int $limit = 10): Collection
    {
        return SemesterResult::query()
            ->where('semester_id', $semesterId)
            ->whereNotNull('average')
            ->with('student')
            ->orderByDesc('average')
            ->limit($limit)
            ->get()
            ->map(fn ($r, $index) => [
                'rank' => $index + 1,
                'student_id' => $r->student_id,
                'student' => $r->student ? [
                    'matricule' => $r->student->matricule,
                    'full_name' => $r->student->full_name,
                ] : null,
                'average' => $r->average,
                'mention' => $r->mention,
                'acquired_credits' => $r->acquired_credits,
                'total_credits' => $r->total_credits,
            ]);
    }

    /**
     * Calculate percentage safely
     */
    protected function percentage($part, $total): float
    {
        return $total > 0 ? round(($part / $total) * 100, 2) : 0;
    }

    /**
     * Calculate standard deviation
     */
    protected function standardDeviation(array $values): ?float
    {
        $count = count($values);

        if ($count === 0) {
            return null;
        }

        $mean = array_sum($values) / $count;
        $squaredDiffs = array_map(fn ($v) => ($v - $mean) ** 2, $values);
        $variance = array_sum($squaredDiffs) / $count;

        return round(sqrt($variance), 2);
    }

    /**
     * Get status indicator color based on success rate
     */
    protected function getStatusIndicator(float $successRate): string
    {
        if ($successRate < 50) {
            return 'red';
        }

        if ($successRate < 70) {
            return 'orange';
        }

        return 'green';
    }

    /**
     * Return empty statistics structure
     */
    protected function emptyStatistics(): array
    {
        return [
            'total_students' => 0,
            'rates' => [
                'success_rate' => 0,
                'admission_rate' => 0,
                'admitted_with_debts_rate' => 0,
                'compensation_rate' => 0,
                'retake_rate' => 0,
                'failure_rate' => 0,
            ],
            'counts' => [
                'admitted' => 0,
                'admitted_with_debts' => 0,
                'deferred_final' => 0,
                'repeating' => 0,
                'to_retake' => 0,
                'with_compensation' => 0,
            ],
            'averages' => [
                'class_average' => null,
                'average_ects' => 0,
                'average_success_rate' => 0,
            ],
            'retake_impact' => [
                'students_with_retake' => 0,
                'improved_by_retake' => 0,
                'retake_success_rate' => 0,
            ],
        ];
    }
}
