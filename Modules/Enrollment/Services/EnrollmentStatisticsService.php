<?php

namespace Modules\Enrollment\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\Group;
use Modules\Enrollment\Entities\PedagogicalEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentEnrollment;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;

class EnrollmentStatisticsService
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get global KPIs for an academic year
     *
     * @return array<string, mixed>
     */
    public function getGlobalKPIs(AcademicYear $year): array
    {
        $cacheKey = "enrollment_kpis_{$year->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($year) {
            $totalStudents = Student::on('tenant')->count();

            $enrollments = StudentEnrollment::on('tenant')
                ->where('academic_year_id', $year->id)
                ->get();

            $newStudents = $enrollments
                ->filter(fn ($e) => $e->is_new_student ?? false)
                ->count();

            $reenrollments = $enrollments
                ->filter(fn ($e) => ! ($e->is_new_student ?? true))
                ->count();

            $activeStudents = Student::on('tenant')
                ->where('status', 'Actif')
                ->count();

            $pedagogicalValidated = PedagogicalEnrollment::on('tenant')
                ->where('academic_year_id', $year->id)
                ->where('status', PedagogicalEnrollment::STATUS_VALIDATED)
                ->count();

            $pedagogicalPending = PedagogicalEnrollment::on('tenant')
                ->where('academic_year_id', $year->id)
                ->where('status', PedagogicalEnrollment::STATUS_ACTIVE)
                ->count();

            $pedagogicalTotal = PedagogicalEnrollment::on('tenant')
                ->where('academic_year_id', $year->id)
                ->count();

            $conversionRate = $activeStudents > 0
                ? round(($pedagogicalValidated / $activeStudents) * 100, 2)
                : 0;

            $validationRate = $pedagogicalTotal > 0
                ? round(($pedagogicalValidated / $pedagogicalTotal) * 100, 2)
                : 0;

            return [
                'total_students' => $totalStudents,
                'active_students' => $activeStudents,
                'new_students' => $newStudents,
                'reenrollments' => $reenrollments,
                'pedagogical_validated' => $pedagogicalValidated,
                'pedagogical_pending' => $pedagogicalPending,
                'pedagogical_total' => $pedagogicalTotal,
                'conversion_rate' => $conversionRate,
                'validation_rate' => $validationRate,
                'academic_year' => [
                    'id' => $year->id,
                    'name' => $year->name,
                ],
            ];
        });
    }

    /**
     * Get enrollments statistics by program
     */
    public function getEnrollmentsByProgram(AcademicYear $year): Collection
    {
        $cacheKey = "enrollment_by_program_{$year->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($year) {
            $programmes = Programme::on('tenant')
                ->where('statut', 'Actif')
                ->with(['programLevels'])
                ->get();

            return $programmes->map(function ($program) use ($year) {
                $enrollments = StudentEnrollment::on('tenant')
                    ->where('academic_year_id', $year->id)
                    ->where('program_id', $program->id)
                    ->with('student')
                    ->get();

                $students = $enrollments->pluck('student')->filter();

                $maleCount = $students->where('sex', 'M')->count();
                $femaleCount = $students->where('sex', 'F')->count();
                $total = $students->count();

                // Calculate average age
                $averageAge = $students->avg(function ($student) {
                    return $student->birthdate ? $student->birthdate->age : null;
                });

                // Get previous year comparison
                $previousYear = AcademicYear::on('tenant')
                    ->where('id', '<', $year->id)
                    ->orderBy('id', 'desc')
                    ->first();

                $previousCount = 0;
                if ($previousYear) {
                    $previousCount = StudentEnrollment::on('tenant')
                        ->where('academic_year_id', $previousYear->id)
                        ->where('program_id', $program->id)
                        ->count();
                }

                $growthRate = $previousCount > 0
                    ? round((($total - $previousCount) / $previousCount) * 100, 2)
                    : 0;

                // Group by level
                $byLevel = $enrollments->groupBy('level')->map->count();

                return [
                    'program' => [
                        'id' => $program->id,
                        'code' => $program->code,
                        'name' => $program->libelle,
                        'type' => $program->type,
                    ],
                    'total' => $total,
                    'male' => $maleCount,
                    'female' => $femaleCount,
                    'gender_ratio' => $total > 0 ? round(($maleCount / $total) * 100, 2) : 0,
                    'average_age' => $averageAge ? round($averageAge, 1) : null,
                    'by_level' => $byLevel,
                    'previous_year_count' => $previousCount,
                    'growth_rate' => $growthRate,
                ];
            })->sortByDesc('total')->values();
        });
    }

    /**
     * Get enrollment trends over multiple years
     */
    public function getEnrollmentTrends(int $yearsCount = 5, ?int $programId = null): array
    {
        $cacheKey = "enrollment_trends_{$yearsCount}".($programId ? "_{$programId}" : '');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($yearsCount, $programId) {
            $years = AcademicYear::on('tenant')
                ->orderBy('start_date', 'desc')
                ->take($yearsCount)
                ->get()
                ->reverse();

            $data = [];
            foreach ($years as $year) {
                $query = StudentEnrollment::on('tenant')
                    ->where('academic_year_id', $year->id);

                if ($programId) {
                    $query->where('program_id', $programId);
                }

                $count = $query->count();

                $data[] = [
                    'year' => $year->name,
                    'year_id' => $year->id,
                    'count' => $count,
                ];
            }

            return $data;
        });
    }

    /**
     * Get demographic analysis
     *
     * @return array<string, mixed>
     */
    public function getDemographicAnalysis(AcademicYear $year): array
    {
        $cacheKey = "enrollment_demographics_{$year->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($year) {
            return [
                'age_distribution' => $this->getAgeDistribution($year),
                'gender_distribution' => $this->getGenderDistribution($year),
                'geographic_distribution' => $this->getGeographicDistribution($year),
                'nationality_distribution' => $this->getNationalityDistribution($year),
            ];
        });
    }

    /**
     * Get age distribution
     *
     * @return array<string, int>
     */
    private function getAgeDistribution(AcademicYear $year): array
    {
        $students = $this->getStudentsForYear($year);

        $distribution = [
            '< 18' => 0,
            '18-20' => 0,
            '21-23' => 0,
            '24-26' => 0,
            '> 26' => 0,
        ];

        foreach ($students as $student) {
            if (! $student->birthdate) {
                continue;
            }

            $age = $student->birthdate->age;

            if ($age < 18) {
                $distribution['< 18']++;
            } elseif ($age <= 20) {
                $distribution['18-20']++;
            } elseif ($age <= 23) {
                $distribution['21-23']++;
            } elseif ($age <= 26) {
                $distribution['24-26']++;
            } else {
                $distribution['> 26']++;
            }
        }

        return $distribution;
    }

    /**
     * Get gender distribution
     *
     * @return array<string, int>
     */
    private function getGenderDistribution(AcademicYear $year): array
    {
        $students = $this->getStudentsForYear($year);

        return [
            'male' => $students->where('sex', 'M')->count(),
            'female' => $students->where('sex', 'F')->count(),
            'other' => $students->whereNotIn('sex', ['M', 'F'])->count(),
        ];
    }

    /**
     * Get geographic distribution (top 10 cities)
     *
     * @return array<string, int>
     */
    private function getGeographicDistribution(AcademicYear $year): array
    {
        $students = $this->getStudentsForYear($year);

        return $students
            ->groupBy('city')
            ->map->count()
            ->sortDesc()
            ->take(10)
            ->toArray();
    }

    /**
     * Get nationality distribution
     *
     * @return array<string, int>
     */
    private function getNationalityDistribution(AcademicYear $year): array
    {
        $students = $this->getStudentsForYear($year);

        return $students
            ->groupBy('nationality')
            ->map->count()
            ->sortDesc()
            ->take(10)
            ->toArray();
    }

    /**
     * Get pedagogical enrollment analysis
     *
     * @return array<string, mixed>
     */
    public function getPedagogicalAnalysis(AcademicYear $year): array
    {
        $cacheKey = "enrollment_pedagogical_{$year->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($year) {
            $enrollments = PedagogicalEnrollment::on('tenant')
                ->where('academic_year_id', $year->id)
                ->get();

            $statusCounts = $enrollments->groupBy('status')->map->count();

            // Groups analysis
            $groups = Group::on('tenant')
                ->where('academic_year_id', $year->id)
                ->withCount('assignments')
                ->get();

            $overfilled = $groups->filter(fn ($g) => $g->assignments_count > $g->capacity_max)->count();
            $underfilled = $groups->filter(fn ($g) => $g->assignments_count < $g->capacity_min)->count();

            return [
                'status_distribution' => $statusCounts,
                'total_enrollments' => $enrollments->count(),
                'groups_statistics' => [
                    'total_groups' => $groups->count(),
                    'overfilled_groups' => $overfilled,
                    'underfilled_groups' => $underfilled,
                    'average_fill_rate' => $groups->avg('fill_rate'),
                ],
                'modules_check_rate' => $this->calculateCheckRate($enrollments, 'modules_check'),
                'groups_check_rate' => $this->calculateCheckRate($enrollments, 'groups_check'),
                'options_check_rate' => $this->calculateCheckRate($enrollments, 'options_check'),
                'prerequisites_check_rate' => $this->calculateCheckRate($enrollments, 'prerequisites_check'),
            ];
        });
    }

    /**
     * Get monthly enrollment trends for current year
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMonthlyTrends(AcademicYear $year): array
    {
        $cacheKey = "enrollment_monthly_{$year->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($year) {
            $enrollments = StudentEnrollment::on('tenant')
                ->where('academic_year_id', $year->id)
                ->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get();

            return $enrollments->map(function ($item) {
                return [
                    'year' => $item->year,
                    'month' => $item->month,
                    'count' => $item->count,
                    'label' => date('M Y', mktime(0, 0, 0, $item->month, 1, $item->year)),
                ];
            })->toArray();
        });
    }

    /**
     * Get status-based statistics
     *
     * @return array<string, int>
     */
    public function getStatusStatistics(): array
    {
        return [
            'active' => Student::on('tenant')->where('status', 'Actif')->count(),
            'suspended' => Student::on('tenant')->where('status', 'Suspendu')->count(),
            'excluded' => Student::on('tenant')->where('status', 'Exclu')->count(),
            'graduated' => Student::on('tenant')->where('status', 'Diplômé')->count(),
            'archived' => Student::on('tenant')->where('status', 'Archivé')->count(),
        ];
    }

    /**
     * Get comparison between two academic years
     *
     * @return array<string, mixed>
     */
    public function getYearComparison(AcademicYear $year1, AcademicYear $year2): array
    {
        $kpis1 = $this->getGlobalKPIs($year1);
        $kpis2 = $this->getGlobalKPIs($year2);

        $calculateDiff = function ($val1, $val2) {
            if ($val2 == 0) {
                return $val1 > 0 ? 100 : 0;
            }

            return round((($val1 - $val2) / $val2) * 100, 2);
        };

        return [
            'year_1' => [
                'year' => $year1->name,
                'kpis' => $kpis1,
            ],
            'year_2' => [
                'year' => $year2->name,
                'kpis' => $kpis2,
            ],
            'differences' => [
                'total_students' => $calculateDiff($kpis1['total_students'], $kpis2['total_students']),
                'active_students' => $calculateDiff($kpis1['active_students'], $kpis2['active_students']),
                'pedagogical_validated' => $calculateDiff($kpis1['pedagogical_validated'], $kpis2['pedagogical_validated']),
            ],
        ];
    }

    /**
     * Get alerts for enrollment issues
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAlerts(AcademicYear $year): array
    {
        $alerts = [];

        // Check for underfilled programs
        $programs = $this->getEnrollmentsByProgram($year);
        foreach ($programs as $program) {
            if ($program['total'] < 10 && $program['previous_year_count'] > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'category' => 'low_enrollment',
                    'message' => "Programme {$program['program']['code']} sous capacité minimale ({$program['total']} inscrits)",
                    'program_id' => $program['program']['id'],
                ];
            }

            if ($program['growth_rate'] < -20) {
                $alerts[] = [
                    'type' => 'danger',
                    'category' => 'enrollment_drop',
                    'message' => "Chute significative des inscriptions pour {$program['program']['code']} ({$program['growth_rate']}%)",
                    'program_id' => $program['program']['id'],
                ];
            }
        }

        // Check for pending enrollments
        $pending = PedagogicalEnrollment::on('tenant')
            ->where('academic_year_id', $year->id)
            ->where('status', PedagogicalEnrollment::STATUS_ACTIVE)
            ->where('created_at', '<', now()->subDays(7))
            ->count();

        if ($pending > 0) {
            $alerts[] = [
                'type' => 'warning',
                'category' => 'pending_validation',
                'message' => "{$pending} inscriptions en attente de validation depuis plus de 7 jours",
            ];
        }

        return $alerts;
    }

    /**
     * Clear all statistics cache for an academic year
     */
    public function clearCache(AcademicYear $year): void
    {
        Cache::forget("enrollment_kpis_{$year->id}");
        Cache::forget("enrollment_by_program_{$year->id}");
        Cache::forget("enrollment_demographics_{$year->id}");
        Cache::forget("enrollment_pedagogical_{$year->id}");
        Cache::forget("enrollment_monthly_{$year->id}");
    }

    /**
     * Get students enrolled in a specific academic year
     */
    private function getStudentsForYear(AcademicYear $year): Collection
    {
        $studentIds = StudentEnrollment::on('tenant')
            ->where('academic_year_id', $year->id)
            ->pluck('student_id');

        return Student::on('tenant')
            ->whereIn('id', $studentIds)
            ->get();
    }

    /**
     * Calculate check rate for a specific field
     */
    private function calculateCheckRate(Collection $enrollments, string $field): float
    {
        if ($enrollments->isEmpty()) {
            return 0;
        }

        $checked = $enrollments->where($field, true)->count();

        return round(($checked / $enrollments->count()) * 100, 2);
    }
}
