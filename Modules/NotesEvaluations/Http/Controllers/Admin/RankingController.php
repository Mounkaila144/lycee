<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Exports\RankingExport;
use Modules\NotesEvaluations\Services\RankingService;

class RankingController extends Controller
{
    public function __construct(
        protected RankingService $rankingService
    ) {}

    /**
     * Calculate ranking for a semester
     * POST /api/admin/semesters/{semester}/calculate-ranking
     */
    public function calculate(Request $request, int $semesterId): JsonResponse
    {
        $programmeId = $request->input('programme_id');

        $result = $this->rankingService->calculateRanking($semesterId, $programmeId);

        if (! $result['success']) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * Get ranking for a semester
     * GET /api/admin/semesters/{semester}/ranking
     */
    public function index(Request $request, int $semesterId): JsonResponse
    {
        $programmeId = $request->query('programme_id');
        $perPage = min($request->query('per_page', 50), 100);
        $paginate = $request->boolean('paginate', true);

        if ($paginate) {
            $ranking = $this->rankingService->getPaginatedRanking($semesterId, $programmeId, $perPage);

            return response()->json([
                'data' => $ranking->map(fn ($r) => $this->formatRankingItem($r)),
                'meta' => [
                    'current_page' => $ranking->currentPage(),
                    'last_page' => $ranking->lastPage(),
                    'per_page' => $ranking->perPage(),
                    'total' => $ranking->total(),
                ],
            ]);
        }

        $ranking = $this->rankingService->getRanking($semesterId, $programmeId);

        return response()->json([
            'data' => $ranking->map(fn ($r) => $this->formatRankingItem($r)),
            'meta' => [
                'total' => $ranking->count(),
            ],
        ]);
    }

    /**
     * Get top performers (podium)
     * GET /api/admin/semesters/{semester}/ranking/top
     */
    public function top(Request $request, int $semesterId): JsonResponse
    {
        $topN = min($request->query('limit', 10), 100);
        $programmeId = $request->query('programme_id');

        $top = $this->rankingService->getTopPerformers($semesterId, $topN, $programmeId);

        return response()->json([
            'data' => $top->map(fn ($r) => $this->formatRankingItem($r)),
        ]);
    }

    /**
     * Get mention distribution
     * GET /api/admin/semesters/{semester}/mention-distribution
     */
    public function mentionDistribution(Request $request, int $semesterId): JsonResponse
    {
        $programmeId = $request->query('programme_id');

        $distribution = $this->rankingService->getMentionDistribution($semesterId, $programmeId);

        return response()->json([
            'data' => $distribution,
        ]);
    }

    /**
     * Get student position
     * GET /api/admin/students/{student}/semesters/{semester}/position
     */
    public function studentPosition(int $studentId, int $semesterId): JsonResponse
    {
        $position = $this->rankingService->getStudentPosition($studentId, $semesterId);

        if (! $position) {
            return response()->json([
                'message' => 'Résultat non trouvé pour cet étudiant.',
            ], 404);
        }

        return response()->json([
            'data' => $position,
        ]);
    }

    /**
     * Get student ranking evolution
     * GET /api/admin/students/{student}/ranking-evolution
     */
    public function studentEvolution(int $studentId): JsonResponse
    {
        $evolution = $this->rankingService->getStudentRankingEvolution($studentId);

        // Calculate trend
        $trend = 'stable';
        if ($evolution->count() >= 2) {
            $first = $evolution->first();
            $last = $evolution->last();

            if ($first && $last) {
                $rankChange = $first['rank'] - $last['rank'];

                if ($rankChange > 0) {
                    $trend = 'improving';
                } elseif ($rankChange < 0) {
                    $trend = 'declining';
                }
            }
        }

        return response()->json([
            'data' => [
                'evolution' => $evolution,
                'trend' => $trend,
            ],
        ]);
    }

    /**
     * Get improving students
     * GET /api/admin/semesters/{semester}/improving-students
     */
    public function improvingStudents(Request $request, int $semesterId): JsonResponse
    {
        $previousSemesterId = $request->query('previous_semester_id');
        $minGain = $request->query('min_gain', 5);

        if (! $previousSemesterId) {
            return response()->json([
                'message' => 'previous_semester_id est requis.',
            ], 422);
        }

        $improving = $this->rankingService->getImprovingStudents($semesterId, $previousSemesterId, $minGain);

        return response()->json([
            'data' => $improving,
            'meta' => [
                'total' => $improving->count(),
            ],
        ]);
    }

    /**
     * Get palmarès data
     * GET /api/admin/semesters/{semester}/palmares
     */
    public function palmares(Request $request, int $semesterId): JsonResponse
    {
        $topN = min($request->query('limit', 10), 50);

        $data = $this->rankingService->getPalmaresData($semesterId, $topN);

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Export ranking to Excel
     * GET /api/admin/semesters/{semester}/ranking/export
     */
    public function export(Request $request, int $semesterId)
    {
        $programmeId = $request->query('programme_id');
        $format = $request->query('format', 'xlsx');

        $filename = sprintf(
            'classement_%s_%s.%s',
            $semesterId,
            now()->format('Ymd'),
            $format
        );

        $ranking = $this->rankingService->getRanking($semesterId, $programmeId);
        $mentionDistribution = $this->rankingService->getMentionDistribution($semesterId, $programmeId);

        return (new RankingExport($ranking, $mentionDistribution))->download($filename);
    }

    /**
     * Format ranking item for response
     */
    protected function formatRankingItem($result): array
    {
        return [
            'id' => $result->id,
            'rank' => $result->rank,
            'total_ranked' => $result->total_ranked,
            'rank_display' => $result->rank_display,
            'student_id' => $result->student_id,
            'student' => $result->student ? [
                'matricule' => $result->student->matricule,
                'full_name' => $result->student->full_name,
                'programme' => $result->student->programme?->name,
            ] : null,
            'average' => $result->average,
            'mention' => $result->mention,
            'mention_badge_color' => match ($result->mention) {
                'Très Bien' => 'gold',
                'Bien' => 'silver',
                'Assez Bien' => 'bronze',
                'Passable' => 'blue',
                default => 'gray',
            },
            'acquired_credits' => $result->acquired_credits,
            'total_credits' => $result->total_credits,
            'success_rate' => $result->success_rate,
        ];
    }
}
