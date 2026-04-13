<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Entities\SemesterResult;

class PerformanceAnalyticsService
{
    /**
     * Get Key Performance Indicators for a semester
     */
    public function getKPIs(int $semesterId, ?int $programmeId = null): array
    {
        $query = SemesterResult::query()
            ->where('semester_id', $semesterId);

        if ($programmeId) {
            $query->whereHas('student', fn ($q) => $q->where('programme_id', $programmeId));
        }

        $results = $query->get();
        $total = $results->count();

        if ($total === 0) {
            return $this->emptyKPIs();
        }

        $admitted = $results->whereIn('final_status', ['admitted', 'admitted_with_debts'])->count();
        $successRate = round(($admitted / $total) * 100, 2);

        // Critical modules (>50% failure)
        $criticalModulesCount = $this->getCriticalModulesCount($semesterId, $programmeId);

        // Calculate trend (compare with previous semester if available)
        $trend = $this->calculateSuccessRateTrend($semesterId, $successRate, $programmeId);

        return [
            'total_students' => $total,
            'success_rate' => $successRate,
            'success_rate_trend' => $trend,
            'class_average' => round($results->avg('average') ?? 0, 2),
            'critical_modules_count' => $criticalModulesCount,
            'admitted_count' => $admitted,
            'deferred_count' => $results->where('final_status', 'deferred_final')->count(),
            'repeating_count' => $results->where('final_status', 'repeating')->count(),
            'alerts' => $this->generateAlerts($successRate, $criticalModulesCount),
        ];
    }

    /**
     * Get weak/critical modules
     */
    public function getWeakModules(int $semesterId, ?int $programmeId = null): Collection
    {
        $moduleGradesQuery = ModuleGrade::query()
            ->where('semester_id', $semesterId)
            ->with(['module', 'module.teachers']);

        if ($programmeId) {
            $moduleGradesQuery->whereHas('student', fn ($q) => $q->where('programme_id', $programmeId));
        }

        $moduleGrades = $moduleGradesQuery->get()->groupBy('module_id');

        return $moduleGrades->map(function ($grades, $moduleId) {
            $module = $grades->first()?->module;
            $averages = $grades->pluck('average')->filter();
            $total = $averages->count();

            if ($total === 0) {
                return null;
            }

            $failedCount = $averages->filter(fn ($a) => $a < 10)->count();
            $failureRate = round(($failedCount / $total) * 100, 2);
            $average = round($averages->avg(), 2);
            $stdDev = $this->calculateStdDev($averages);

            // Determine if critical
            $isCritical = $failureRate > 50 || $average < 10;

            // Generate recommendations
            $recommendations = $this->generateModuleRecommendations($failureRate, $average, $stdDev);

            return [
                'module_id' => $moduleId,
                'module' => $module ? [
                    'code' => $module->code,
                    'name' => $module->name,
                    'credits_ects' => $module->credits_ects,
                ] : null,
                'teacher' => $module?->teachers?->first() ? [
                    'id' => $module->teachers->first()->id,
                    'name' => $module->teachers->first()->full_name,
                ] : null,
                'total_students' => $total,
                'failed_count' => $failedCount,
                'failure_rate' => $failureRate,
                'average' => $average,
                'std_dev' => $stdDev,
                'min' => round($averages->min(), 2),
                'max' => round($averages->max(), 2),
                'is_critical' => $isCritical,
                'status_indicator' => $isCritical ? 'red' : ($failureRate > 30 ? 'orange' : 'green'),
                'recommendations' => $recommendations,
            ];
        })
            ->filter()
            ->filter(fn ($m) => $m['is_critical'] || $m['failure_rate'] > 30)
            ->sortByDesc('failure_rate')
            ->values();
    }

    /**
     * Get cohort analysis (student segmentation)
     */
    public function getCohortAnalysis(int $semesterId, ?int $programmeId = null): array
    {
        $query = SemesterResult::query()
            ->where('semester_id', $semesterId)
            ->whereNotNull('average');

        if ($programmeId) {
            $query->whereHas('student', fn ($q) => $q->where('programme_id', $programmeId));
        }

        $results = $query->get();
        $total = $results->count();

        if ($total === 0) {
            return ['cohorts' => [], 'total' => 0];
        }

        $cohorts = [
            'excellent' => [
                'label' => 'Excellents',
                'range' => '14-20',
                'count' => $results->where('average', '>=', 14)->count(),
                'color' => 'gold',
            ],
            'good' => [
                'label' => 'Bons',
                'range' => '12-14',
                'count' => $results->filter(fn ($r) => $r->average >= 12 && $r->average < 14)->count(),
                'color' => 'green',
            ],
            'average' => [
                'label' => 'Moyens',
                'range' => '10-12',
                'count' => $results->filter(fn ($r) => $r->average >= 10 && $r->average < 12)->count(),
                'color' => 'blue',
            ],
            'weak' => [
                'label' => 'Faibles',
                'range' => '8-10',
                'count' => $results->filter(fn ($r) => $r->average >= 8 && $r->average < 10)->count(),
                'color' => 'orange',
            ],
            'failing' => [
                'label' => 'En échec',
                'range' => '<8',
                'count' => $results->where('average', '<', 8)->count(),
                'color' => 'red',
            ],
        ];

        // Add percentages
        foreach ($cohorts as $key => &$cohort) {
            $cohort['percentage'] = round(($cohort['count'] / $total) * 100, 2);
        }

        return [
            'cohorts' => $cohorts,
            'total' => $total,
        ];
    }

    /**
     * Get at-risk students based on simple predictive factors
     */
    public function getAtRiskStudents(int $semesterId, float $threshold = 60): Collection
    {
        $results = SemesterResult::query()
            ->where('semester_id', $semesterId)
            ->whereNotNull('average')
            ->where('average', '<', 10)
            ->with('student')
            ->get();

        return $results->map(function ($result) {
            // Calculate risk score based on multiple factors
            $riskScore = 0;
            $factors = [];

            // Factor 1: Current average
            if ($result->average < 6) {
                $riskScore += 40;
                $factors[] = 'Moyenne très faible (<6)';
            } elseif ($result->average < 8) {
                $riskScore += 25;
                $factors[] = 'Moyenne faible (<8)';
            } elseif ($result->average < 10) {
                $riskScore += 15;
                $factors[] = 'Moyenne insuffisante (<10)';
            }

            // Factor 2: Failed modules count
            if ($result->failed_modules_count > 3) {
                $riskScore += 30;
                $factors[] = "Nombreux modules échoués ({$result->failed_modules_count})";
            } elseif ($result->failed_modules_count > 1) {
                $riskScore += 15;
                $factors[] = "Plusieurs modules échoués ({$result->failed_modules_count})";
            }

            // Factor 3: Missing credits
            if ($result->missing_credits > 12) {
                $riskScore += 20;
                $factors[] = "Crédits manquants importants ({$result->missing_credits})";
            }

            // Factor 4: Blocked by eliminatory
            if ($result->validation_blocked_by_eliminatory) {
                $riskScore += 20;
                $factors[] = 'Bloqué par module éliminatoire';
            }

            return [
                'student_id' => $result->student_id,
                'student' => $result->student ? [
                    'matricule' => $result->student->matricule,
                    'full_name' => $result->student->full_name,
                    'programme' => $result->student->programme?->name,
                ] : null,
                'average' => $result->average,
                'failed_modules_count' => $result->failed_modules_count,
                'risk_score' => min($riskScore, 100),
                'risk_level' => $this->getRiskLevel($riskScore),
                'factors' => $factors,
            ];
        })
            ->filter(fn ($s) => $s['risk_score'] >= $threshold)
            ->sortByDesc('risk_score')
            ->values();
    }

    /**
     * Get historical comparison across semesters
     */
    public function getHistoricalComparison(int $academicYearId): Collection
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
                DB::raw('SUM(CASE WHEN semester_results.final_status = "deferred_final" THEN 1 ELSE 0 END) as deferred')
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
                    'failure_rate' => $row->total > 0 ? round(($row->deferred / $row->total) * 100, 2) : 0,
                ];
            });
    }

    /**
     * Get correlation matrix between modules
     */
    public function getCorrelationMatrix(int $semesterId, int $maxModules = 10): array
    {
        // Get modules with grades
        $modules = ModuleGrade::where('semester_id', $semesterId)
            ->select('module_id')
            ->distinct()
            ->with('module:id,code,name')
            ->limit($maxModules)
            ->get()
            ->pluck('module')
            ->filter();

        if ($modules->count() < 2) {
            return ['modules' => [], 'matrix' => []];
        }

        $moduleIds = $modules->pluck('id');
        $matrix = [];

        foreach ($moduleIds as $moduleId1) {
            foreach ($moduleIds as $moduleId2) {
                if (! isset($matrix[$moduleId1])) {
                    $matrix[$moduleId1] = [];
                }

                if ($moduleId1 === $moduleId2) {
                    $matrix[$moduleId1][$moduleId2] = 1.0;
                } else {
                    $correlation = $this->calculateCorrelation($moduleId1, $moduleId2, $semesterId);
                    $matrix[$moduleId1][$moduleId2] = $correlation;
                }
            }
        }

        return [
            'modules' => $modules->map(fn ($m) => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
            ])->values(),
            'matrix' => $matrix,
        ];
    }

    /**
     * Get full analytics dashboard data
     */
    public function getDashboardData(int $semesterId, ?int $programmeId = null): array
    {
        return [
            'kpis' => $this->getKPIs($semesterId, $programmeId),
            'weak_modules' => $this->getWeakModules($semesterId, $programmeId)->take(5),
            'cohort_analysis' => $this->getCohortAnalysis($semesterId, $programmeId),
            'at_risk_students' => $this->getAtRiskStudents($semesterId, 60)->take(10),
        ];
    }

    /**
     * Calculate standard deviation
     */
    protected function calculateStdDev(Collection $values): float
    {
        $count = $values->count();

        if ($count === 0) {
            return 0;
        }

        $mean = $values->avg();
        $squaredDiffs = $values->map(fn ($v) => ($v - $mean) ** 2);
        $variance = $squaredDiffs->avg();

        return round(sqrt($variance), 2);
    }

    /**
     * Calculate Pearson correlation between two modules
     */
    protected function calculateCorrelation(int $moduleId1, int $moduleId2, int $semesterId): float
    {
        $grades1 = ModuleGrade::where('module_id', $moduleId1)
            ->where('semester_id', $semesterId)
            ->whereNotNull('average')
            ->pluck('average', 'student_id');

        $grades2 = ModuleGrade::where('module_id', $moduleId2)
            ->where('semester_id', $semesterId)
            ->whereNotNull('average')
            ->pluck('average', 'student_id');

        $commonStudents = $grades1->keys()->intersect($grades2->keys());

        if ($commonStudents->count() < 3) {
            return 0;
        }

        $x = $commonStudents->map(fn ($id) => $grades1[$id])->values();
        $y = $commonStudents->map(fn ($id) => $grades2[$id])->values();

        $n = $x->count();
        $sumX = $x->sum();
        $sumY = $y->sum();
        $sumXY = $x->zip($y)->map(fn ($pair) => $pair[0] * $pair[1])->sum();
        $sumX2 = $x->map(fn ($v) => $v * $v)->sum();
        $sumY2 = $y->map(fn ($v) => $v * $v)->sum();

        $numerator = ($n * $sumXY) - ($sumX * $sumY);
        $denominator = sqrt((($n * $sumX2) - ($sumX * $sumX)) * (($n * $sumY2) - ($sumY * $sumY)));

        return $denominator != 0 ? round($numerator / $denominator, 2) : 0;
    }

    /**
     * Get count of critical modules
     */
    protected function getCriticalModulesCount(int $semesterId, ?int $programmeId = null): int
    {
        $moduleGradesQuery = ModuleGrade::query()
            ->where('semester_id', $semesterId);

        if ($programmeId) {
            $moduleGradesQuery->whereHas('student', fn ($q) => $q->where('programme_id', $programmeId));
        }

        $moduleGrades = $moduleGradesQuery->get()->groupBy('module_id');

        return $moduleGrades->filter(function ($grades) {
            $averages = $grades->pluck('average')->filter();
            $total = $averages->count();

            if ($total === 0) {
                return false;
            }

            $failedCount = $averages->filter(fn ($a) => $a < 10)->count();
            $failureRate = $failedCount / $total;

            return $failureRate > 0.5;
        })->count();
    }

    /**
     * Calculate success rate trend
     */
    protected function calculateSuccessRateTrend(int $semesterId, float $currentRate, ?int $programmeId = null): array
    {
        // Get previous semester result
        $previousResult = DB::connection('tenant')
            ->table('semester_results')
            ->join('semesters', 'semester_results.semester_id', '=', 'semesters.id')
            ->where('semesters.id', '<', $semesterId)
            ->when($programmeId, function ($q) use ($programmeId) {
                $q->join('students', 'semester_results.student_id', '=', 'students.id')
                    ->where('students.programme_id', $programmeId);
            })
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN semester_results.final_status IN ("admitted", "admitted_with_debts") THEN 1 ELSE 0 END) as admitted')
            )
            ->orderByDesc('semesters.id')
            ->first();

        if (! $previousResult || $previousResult->total == 0) {
            return ['direction' => 'stable', 'change' => 0];
        }

        $previousRate = round(($previousResult->admitted / $previousResult->total) * 100, 2);
        $change = $currentRate - $previousRate;

        return [
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable'),
            'change' => abs(round($change, 2)),
            'previous_rate' => $previousRate,
        ];
    }

    /**
     * Generate alerts based on KPIs
     */
    protected function generateAlerts(float $successRate, int $criticalModulesCount): array
    {
        $alerts = [];

        if ($successRate < 50) {
            $alerts[] = [
                'level' => 'critical',
                'message' => "Taux de réussite critique ({$successRate}%)",
            ];
        } elseif ($successRate < 60) {
            $alerts[] = [
                'level' => 'warning',
                'message' => "Taux de réussite faible ({$successRate}%)",
            ];
        }

        if ($criticalModulesCount > 3) {
            $alerts[] = [
                'level' => 'critical',
                'message' => "{$criticalModulesCount} modules en situation critique",
            ];
        } elseif ($criticalModulesCount > 0) {
            $alerts[] = [
                'level' => 'warning',
                'message' => "{$criticalModulesCount} module(s) à surveiller",
            ];
        }

        return $alerts;
    }

    /**
     * Generate recommendations for weak modules
     */
    protected function generateModuleRecommendations(float $failureRate, float $average, float $stdDev): array
    {
        $recommendations = [];

        if ($failureRate > 60) {
            $recommendations[] = 'Révision du programme ou de la pédagogie recommandée';
        } elseif ($failureRate > 50) {
            $recommendations[] = 'Mise en place de séances de soutien recommandée';
        }

        if ($stdDev > 5) {
            $recommendations[] = 'Forte hétérogénéité - envisager des groupes de niveau';
        }

        if ($average < 8) {
            $recommendations[] = 'Analyse des prérequis étudiants recommandée';
        }

        return $recommendations;
    }

    /**
     * Get risk level label
     */
    protected function getRiskLevel(float $score): string
    {
        if ($score >= 80) {
            return 'critical';
        }

        if ($score >= 60) {
            return 'high';
        }

        if ($score >= 40) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Empty KPIs structure
     */
    protected function emptyKPIs(): array
    {
        return [
            'total_students' => 0,
            'success_rate' => 0,
            'success_rate_trend' => ['direction' => 'stable', 'change' => 0],
            'class_average' => 0,
            'critical_modules_count' => 0,
            'admitted_count' => 0,
            'deferred_count' => 0,
            'repeating_count' => 0,
            'alerts' => [],
        ];
    }
}
