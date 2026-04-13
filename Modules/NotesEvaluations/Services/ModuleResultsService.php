<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Collection;
use Modules\NotesEvaluations\Entities\ModuleGrade;
use Modules\NotesEvaluations\Entities\ModuleResult;

class ModuleResultsService
{
    /**
     * Generate results for a module in a specific semester
     */
    public function generate(int $moduleId, int $semesterId): ModuleResult
    {
        $moduleGrades = ModuleGrade::where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->with('student')
            ->get();

        $totalStudents = $moduleGrades->count();

        if ($totalStudents === 0) {
            return $this->createEmptyResult($moduleId, $semesterId);
        }

        $validGrades = $moduleGrades->filter(fn ($g) => $g->average !== null);
        $absences = $moduleGrades->filter(fn ($g) => $g->average === null);

        $averages = $validGrades->pluck('average')->map(fn ($v) => (float) $v)->sort()->values();

        $statistics = [
            'total_students' => $totalStudents,
            'class_average' => $averages->isNotEmpty() ? round($averages->avg(), 2) : null,
            'min_grade' => $averages->isNotEmpty() ? $averages->min() : null,
            'max_grade' => $averages->isNotEmpty() ? $averages->max() : null,
            'median' => $this->calculateMedian($averages),
            'standard_deviation' => $this->calculateStdDev($averages),
            'pass_rate' => $this->calculatePassRate($validGrades, $totalStudents),
            'absence_rate' => $totalStudents > 0 ? round(($absences->count() / $totalStudents) * 100, 2) : 0,
            'distribution' => $this->calculateDistribution($averages),
        ];

        // Calculate rankings
        $this->calculateRankings($moduleGrades);

        return ModuleResult::updateOrCreate(
            [
                'module_id' => $moduleId,
                'semester_id' => $semesterId,
            ],
            array_merge($statistics, ['generated_at' => now()])
        );
    }

    /**
     * Create an empty result when no grades exist
     */
    private function createEmptyResult(int $moduleId, int $semesterId): ModuleResult
    {
        return ModuleResult::updateOrCreate(
            [
                'module_id' => $moduleId,
                'semester_id' => $semesterId,
            ],
            [
                'total_students' => 0,
                'class_average' => null,
                'min_grade' => null,
                'max_grade' => null,
                'median' => null,
                'standard_deviation' => null,
                'pass_rate' => null,
                'absence_rate' => null,
                'distribution' => ['0-5' => 0, '5-10' => 0, '10-15' => 0, '15-20' => 0],
                'generated_at' => now(),
            ]
        );
    }

    /**
     * Calculate the median of a collection of values
     */
    public function calculateMedian(Collection $values): ?float
    {
        $count = $values->count();
        if ($count === 0) {
            return null;
        }

        $sorted = $values->sort()->values();
        $middle = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return round(($sorted[$middle - 1] + $sorted[$middle]) / 2, 2);
        }

        return round($sorted[$middle], 2);
    }

    /**
     * Calculate standard deviation
     */
    public function calculateStdDev(Collection $values): ?float
    {
        $count = $values->count();
        if ($count === 0) {
            return null;
        }

        $mean = $values->avg();
        $variance = $values->map(fn ($v) => pow($v - $mean, 2))->avg();

        return round(sqrt($variance), 2);
    }

    /**
     * Calculate pass rate based on valid grades
     */
    public function calculatePassRate(Collection $validGrades, int $total): ?float
    {
        if ($total === 0) {
            return null;
        }

        $passed = $validGrades->filter(fn ($g) => (float) $g->average >= 10)->count();

        return round(($passed / $total) * 100, 2);
    }

    /**
     * Calculate distribution of grades in brackets
     */
    public function calculateDistribution(Collection $averages): array
    {
        $distribution = [
            '0-5' => 0,
            '5-10' => 0,
            '10-15' => 0,
            '15-20' => 0,
        ];

        foreach ($averages as $avg) {
            if ($avg < 5) {
                $distribution['0-5']++;
            } elseif ($avg < 10) {
                $distribution['5-10']++;
            } elseif ($avg < 15) {
                $distribution['10-15']++;
            } else {
                $distribution['15-20']++;
            }
        }

        return $distribution;
    }

    /**
     * Calculate and update rankings for all students in the module
     */
    public function calculateRankings(Collection $moduleGrades): void
    {
        // Filter out students with null averages and sort by average descending
        $sorted = $moduleGrades->filter(fn ($g) => $g->average !== null)
            ->sortByDesc(fn ($g) => (float) $g->average)
            ->values();

        $totalRanked = $sorted->count();

        if ($totalRanked === 0) {
            return;
        }

        $currentRank = 1;
        $previousAverage = null;
        $sameRankCount = 0;

        foreach ($sorted as $index => $grade) {
            $currentAverage = (float) $grade->average;

            if ($previousAverage !== null && $currentAverage < $previousAverage) {
                $currentRank += $sameRankCount;
                $sameRankCount = 1;
            } else {
                $sameRankCount++;
            }

            $grade->update([
                'rank' => $currentRank,
                'total_ranked' => $totalRanked,
            ]);

            $previousAverage = $currentAverage;
        }

        // Set rank to null for absent students
        $moduleGrades->filter(fn ($g) => $g->average === null)
            ->each(fn ($g) => $g->update(['rank' => null, 'total_ranked' => null]));
    }

    /**
     * Get mention based on average
     */
    public function getMention(float $average): string
    {
        if ($average >= 16) {
            return 'Très Bien';
        }

        if ($average >= 14) {
            return 'Bien';
        }

        if ($average >= 12) {
            return 'Assez Bien';
        }

        if ($average >= 10) {
            return 'Passable';
        }

        return 'Non admis';
    }

    /**
     * Get students grouped by status
     *
     * @return array{validated: Collection, failed: Collection, absent: Collection}
     */
    public function getStudentsByStatus(int $moduleId, int $semesterId): array
    {
        $moduleGrades = ModuleGrade::where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->with('student')
            ->get();

        return [
            'validated' => $moduleGrades->filter(fn ($g) => $g->average !== null && (float) $g->average >= 10)
                ->sortByDesc('average')
                ->values(),
            'failed' => $moduleGrades->filter(fn ($g) => $g->average !== null && (float) $g->average < 10)
                ->sortByDesc('average')
                ->values(),
            'absent' => $moduleGrades->filter(fn ($g) => $g->average === null)
                ->sortBy('student.lastname')
                ->values(),
        ];
    }

    /**
     * Publish module results
     */
    public function publish(int $moduleId, int $semesterId): ?ModuleResult
    {
        $result = ModuleResult::where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->first();

        if ($result) {
            $result->publish();
        }

        return $result;
    }

    /**
     * Get module result with additional computed data
     */
    public function getResult(int $moduleId, int $semesterId): ?ModuleResult
    {
        return ModuleResult::where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->with('module', 'semester')
            ->first();
    }
}
