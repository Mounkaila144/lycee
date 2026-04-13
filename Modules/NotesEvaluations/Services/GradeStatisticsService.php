<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Collection;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;

class GradeStatisticsService
{
    /**
     * Calculate statistics for an evaluation
     *
     * @return array{
     *     count: int,
     *     average: float,
     *     min: float|null,
     *     max: float|null,
     *     median: float,
     *     std_dev: float,
     *     pass_rate: float,
     *     fail_rate: float,
     *     absent_count: int,
     *     distribution: array<string, int>
     * }
     */
    public function calculateStats(ModuleEvaluationConfig $evaluation): array
    {
        $grades = Grade::where('evaluation_id', $evaluation->id)
            ->where('is_absent', false)
            ->whereNotNull('score')
            ->pluck('score');

        $absentCount = Grade::where('evaluation_id', $evaluation->id)
            ->where('is_absent', true)
            ->count();

        $totalCount = $grades->count();

        if ($totalCount === 0) {
            return [
                'count' => 0,
                'average' => 0,
                'min' => null,
                'max' => null,
                'median' => 0,
                'std_dev' => 0,
                'pass_rate' => 0,
                'fail_rate' => 0,
                'absent_count' => $absentCount,
                'distribution' => $this->getEmptyDistribution(),
            ];
        }

        $passCount = $grades->filter(fn ($g) => $g >= 10)->count();
        $failCount = $totalCount - $passCount;

        return [
            'count' => $totalCount,
            'average' => round($grades->avg(), 2),
            'min' => $grades->min(),
            'max' => $grades->max(),
            'median' => $this->median($grades),
            'std_dev' => round($this->standardDeviation($grades), 2),
            'pass_rate' => round(($passCount / $totalCount) * 100, 2),
            'fail_rate' => round(($failCount / $totalCount) * 100, 2),
            'absent_count' => $absentCount,
            'distribution' => $this->getDistribution($grades),
        ];
    }

    /**
     * Get grade distribution in ranges
     *
     * @return array<string, int>
     */
    public function getDistribution(Collection $grades): array
    {
        $ranges = [
            '0-5' => 0,
            '5-8' => 0,
            '8-10' => 0,
            '10-12' => 0,
            '12-14' => 0,
            '14-16' => 0,
            '16-18' => 0,
            '18-20' => 0,
        ];

        foreach ($grades as $grade) {
            $grade = (float) $grade;

            if ($grade < 5) {
                $ranges['0-5']++;
            } elseif ($grade < 8) {
                $ranges['5-8']++;
            } elseif ($grade < 10) {
                $ranges['8-10']++;
            } elseif ($grade < 12) {
                $ranges['10-12']++;
            } elseif ($grade < 14) {
                $ranges['12-14']++;
            } elseif ($grade < 16) {
                $ranges['14-16']++;
            } elseif ($grade < 18) {
                $ranges['16-18']++;
            } else {
                $ranges['18-20']++;
            }
        }

        return $ranges;
    }

    /**
     * Get empty distribution array
     *
     * @return array<string, int>
     */
    private function getEmptyDistribution(): array
    {
        return [
            '0-5' => 0,
            '5-8' => 0,
            '8-10' => 0,
            '10-12' => 0,
            '12-14' => 0,
            '14-16' => 0,
            '16-18' => 0,
            '18-20' => 0,
        ];
    }

    /**
     * Calculate median
     */
    public function median(Collection $values): float
    {
        if ($values->isEmpty()) {
            return 0;
        }

        $sorted = $values->sort()->values();
        $count = $sorted->count();
        $middle = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return round(($sorted[$middle - 1] + $sorted[$middle]) / 2, 2);
        }

        return round($sorted[$middle], 2);
    }

    /**
     * Calculate standard deviation
     */
    public function standardDeviation(Collection $values): float
    {
        if ($values->count() <= 1) {
            return 0;
        }

        $avg = $values->avg();
        $variance = $values->map(fn ($v) => pow($v - $avg, 2))->avg();

        return sqrt($variance);
    }

    /**
     * Detect statistical anomalies
     *
     * @return array<string>
     */
    public function detectAnomalies(array $stats): array
    {
        $anomalies = [];

        if ($stats['count'] > 0) {
            if ($stats['average'] < 8) {
                $anomalies[] = "Moyenne très basse ({$stats['average']}/20)";
            }

            if ($stats['average'] > 16) {
                $anomalies[] = "Moyenne très élevée ({$stats['average']}/20)";
            }

            if ($stats['std_dev'] < 2 && $stats['count'] >= 10) {
                $anomalies[] = "Faible dispersion des notes (écart-type: {$stats['std_dev']})";
            }

            if ($stats['std_dev'] > 6) {
                $anomalies[] = "Forte dispersion des notes (écart-type: {$stats['std_dev']})";
            }

            if ($stats['fail_rate'] > 50) {
                $anomalies[] = "Taux d'échec élevé ({$stats['fail_rate']}%)";
            }

            if ($stats['pass_rate'] === 100 && $stats['count'] >= 10) {
                $anomalies[] = 'Aucun échec (100% de réussite)';
            }
        }

        return $anomalies;
    }

    /**
     * Get summary for multiple evaluations (module level)
     */
    public function getModuleSummary(int $moduleId): array
    {
        $evaluations = ModuleEvaluationConfig::where('module_id', $moduleId)->get();

        $summary = [
            'total_evaluations' => $evaluations->count(),
            'completed_evaluations' => 0,
            'pending_evaluations' => 0,
            'average_scores' => [],
            'overall_pass_rate' => 0,
        ];

        $passRates = [];

        foreach ($evaluations as $evaluation) {
            $stats = $this->calculateStats($evaluation);

            if ($stats['count'] > 0) {
                $summary['completed_evaluations']++;
                $summary['average_scores'][$evaluation->name] = $stats['average'];
                $passRates[] = $stats['pass_rate'];
            } else {
                $summary['pending_evaluations']++;
            }
        }

        if (count($passRates) > 0) {
            $summary['overall_pass_rate'] = round(array_sum($passRates) / count($passRates), 2);
        }

        return $summary;
    }
}
