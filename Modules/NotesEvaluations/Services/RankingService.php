<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\NotesEvaluations\Entities\SemesterResult;
use Modules\StructureAcademique\Entities\Semester;

class RankingService
{
    /**
     * Calculate and store rankings for a semester
     */
    public function calculateRanking(int $semesterId, ?int $programmeId = null): array
    {
        $query = SemesterResult::query()
            ->where('semester_id', $semesterId)
            ->whereNotNull('average');

        if ($programmeId) {
            $query->whereHas('student', fn ($q) => $q->where('programme_id', $programmeId));
        }

        $results = $query->orderByDesc('average')->get();

        if ($results->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Aucun résultat à classer.',
                'ranked' => 0,
            ];
        }

        $currentRank = 1;
        $previousAverage = null;
        $studentsWithSameRank = 0;
        $totalRanked = $results->count();

        DB::beginTransaction();
        try {
            foreach ($results as $result) {
                // Handle ties (ex-aequo)
                if ($previousAverage !== null && $result->average < $previousAverage) {
                    $currentRank += $studentsWithSameRank;
                    $studentsWithSameRank = 1;
                } else {
                    $studentsWithSameRank++;
                }

                $result->update([
                    'rank' => $currentRank,
                    'total_ranked' => $totalRanked,
                ]);

                $previousAverage = $result->average;
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Classement calculé avec succès.',
                'ranked' => $totalRanked,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get ranking for a semester with filters
     */
    public function getRanking(
        int $semesterId,
        ?int $programmeId = null,
        ?int $limit = null
    ): Collection {
        $query = SemesterResult::query()
            ->where('semester_id', $semesterId)
            ->with(['student', 'student.programme'])
            ->whereNotNull('rank');

        if ($programmeId) {
            $query->whereHas('student', fn ($q) => $q->where('programme_id', $programmeId));
        }

        $query->orderBy('rank');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get paginated ranking
     */
    public function getPaginatedRanking(
        int $semesterId,
        ?int $programmeId = null,
        int $perPage = 50
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $query = SemesterResult::query()
            ->where('semester_id', $semesterId)
            ->with(['student', 'student.programme'])
            ->whereNotNull('rank')
            ->orderBy('rank');

        if ($programmeId) {
            $query->whereHas('student', fn ($q) => $q->where('programme_id', $programmeId));
        }

        return $query->paginate($perPage);
    }

    /**
     * Get student position and comparison
     */
    public function getStudentPosition(int $studentId, int $semesterId): ?array
    {
        $result = SemesterResult::where([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
        ])->first();

        if (! $result) {
            return null;
        }

        $classAverage = SemesterResult::where('semester_id', $semesterId)
            ->whereNotNull('average')
            ->avg('average');

        return [
            'rank' => $result->rank,
            'total_students' => $result->total_ranked,
            'rank_display' => $result->rank_display,
            'average' => $result->average,
            'mention' => $result->mention,
            'mention_badge_color' => $this->getMentionBadgeColor($result->mention),
            'class_average' => round($classAverage, 2),
            'gap_from_average' => round($result->average - $classAverage, 2),
            'percentile' => $result->total_ranked > 0
                ? round((($result->total_ranked - $result->rank + 1) / $result->total_ranked) * 100, 1)
                : null,
            'acquired_credits' => $result->acquired_credits,
            'total_credits' => $result->total_credits,
        ];
    }

    /**
     * Get student ranking evolution across semesters
     */
    public function getStudentRankingEvolution(int $studentId): Collection
    {
        return SemesterResult::query()
            ->where('student_id', $studentId)
            ->with('semester')
            ->whereNotNull('rank')
            ->orderBy('semester_id')
            ->get()
            ->map(fn ($r) => [
                'semester_id' => $r->semester_id,
                'semester_name' => $r->semester?->name,
                'rank' => $r->rank,
                'total_ranked' => $r->total_ranked,
                'average' => $r->average,
                'mention' => $r->mention,
            ]);
    }

    /**
     * Get mention distribution for a semester
     */
    public function getMentionDistribution(int $semesterId, ?int $programmeId = null): array
    {
        $query = SemesterResult::query()
            ->where('semester_id', $semesterId)
            ->whereNotNull('average');

        if ($programmeId) {
            $query->whereHas('student', fn ($q) => $q->where('programme_id', $programmeId));
        }

        $results = $query->get();
        $total = $results->count();

        $mentions = ['Très Bien', 'Bien', 'Assez Bien', 'Passable', 'Non admis'];
        $distribution = [];

        foreach ($mentions as $mention) {
            $count = $results->filter(fn ($r) => $r->mention === $mention)->count();
            $distribution[$mention] = [
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0,
                'color' => $this->getMentionBadgeColor($mention),
            ];
        }

        return [
            'total' => $total,
            'distribution' => $distribution,
        ];
    }

    /**
     * Get top N performers
     */
    public function getTopPerformers(int $semesterId, int $topN = 10, ?int $programmeId = null): Collection
    {
        return $this->getRanking($semesterId, $programmeId, $topN);
    }

    /**
     * Get students who improved their ranking
     */
    public function getImprovingStudents(int $currentSemesterId, int $previousSemesterId, int $minGain = 5): Collection
    {
        $currentResults = SemesterResult::where('semester_id', $currentSemesterId)
            ->whereNotNull('rank')
            ->pluck('rank', 'student_id');

        $previousResults = SemesterResult::where('semester_id', $previousSemesterId)
            ->whereNotNull('rank')
            ->pluck('rank', 'student_id');

        $improvements = [];

        foreach ($currentResults as $studentId => $currentRank) {
            if (isset($previousResults[$studentId])) {
                $previousRank = $previousResults[$studentId];
                $gain = $previousRank - $currentRank;

                if ($gain >= $minGain) {
                    $improvements[] = [
                        'student_id' => $studentId,
                        'previous_rank' => $previousRank,
                        'current_rank' => $currentRank,
                        'gain' => $gain,
                    ];
                }
            }
        }

        // Sort by gain descending
        usort($improvements, fn ($a, $b) => $b['gain'] <=> $a['gain']);

        // Load student data
        $studentIds = collect($improvements)->pluck('student_id');
        $students = \Modules\Enrollment\Entities\Student::whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');

        return collect($improvements)->map(function ($item) use ($students) {
            $student = $students->get($item['student_id']);

            return array_merge($item, [
                'student' => $student ? [
                    'matricule' => $student->matricule,
                    'full_name' => $student->full_name,
                ] : null,
            ]);
        });
    }

    /**
     * Get mention badge color
     */
    protected function getMentionBadgeColor(string $mention): string
    {
        return match ($mention) {
            'Très Bien' => 'gold',
            'Bien' => 'silver',
            'Assez Bien' => 'bronze',
            'Passable' => 'blue',
            default => 'gray',
        };
    }

    /**
     * Get palmarès data for PDF generation
     */
    public function getPalmaresData(int $semesterId, int $topN = 10): array
    {
        $semester = Semester::with('academicYear')->find($semesterId);
        $ranking = $this->getRanking($semesterId, null, $topN);

        return [
            'semester' => $semester ? [
                'id' => $semester->id,
                'name' => $semester->name,
                'academic_year' => $semester->academicYear?->name,
            ] : null,
            'ranking' => $ranking->map(fn ($r) => [
                'rank' => $r->rank,
                'matricule' => $r->student?->matricule,
                'full_name' => $r->student?->full_name,
                'programme' => $r->student?->programme?->name,
                'average' => $r->average,
                'mention' => $r->mention,
                'mention_color' => $this->getMentionBadgeColor($r->mention),
                'acquired_credits' => $r->acquired_credits,
                'total_credits' => $r->total_credits,
            ]),
            'generated_at' => now()->format('d/m/Y H:i'),
            'total_students' => $ranking->first()?->total_ranked ?? 0,
        ];
    }
}
