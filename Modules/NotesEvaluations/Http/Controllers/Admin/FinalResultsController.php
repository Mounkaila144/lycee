<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Entities\SemesterResult;
use Modules\NotesEvaluations\Services\FinalResultsService;

class FinalResultsController extends Controller
{
    public function __construct(
        protected FinalResultsService $finalResultsService
    ) {}

    /**
     * Check if final results can be published
     * GET /api/admin/semesters/{semester}/final-results/can-publish
     */
    public function canPublish(int $semesterId): JsonResponse
    {
        $result = $this->finalResultsService->canPublishFinalResults($semesterId);

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Publish final results for a semester
     * POST /api/admin/semesters/{semester}/publish-final-results
     */
    public function publish(Request $request, int $semesterId): JsonResponse
    {
        // Check if already locked
        if ($this->finalResultsService->isSemesterLocked($semesterId)) {
            return response()->json([
                'message' => 'Impossible de publier: l\'année est déjà verrouillée.',
            ], 422);
        }

        // Check prerequisites
        $canPublish = $this->finalResultsService->canPublishFinalResults($semesterId);

        if (! $canPublish['can_publish']) {
            return response()->json([
                'message' => 'Impossible de publier les résultats finaux.',
                'issues' => $canPublish['issues'],
            ], 422);
        }

        $notifyStudents = $request->boolean('notify_students', true);
        $generateAttestations = $request->boolean('generate_attestations', true);

        $result = $this->finalResultsService->publishFinalResults(
            $semesterId,
            $notifyStudents,
            $generateAttestations
        );

        return response()->json([
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * Get final statistics for a semester
     * GET /api/admin/semesters/{semester}/final-statistics
     */
    public function statistics(int $semesterId): JsonResponse
    {
        $stats = $this->finalResultsService->getFinalStatistics($semesterId);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get final results by status
     * GET /api/admin/semesters/{semester}/final-results
     */
    public function index(Request $request, int $semesterId): JsonResponse
    {
        $status = $request->query('status');

        $results = $this->finalResultsService->getResultsByStatus($semesterId, $status);

        return response()->json([
            'data' => $results->map(function ($result) {
                return [
                    'id' => $result->id,
                    'student_id' => $result->student_id,
                    'student' => $result->student ? [
                        'matricule' => $result->student->matricule,
                        'full_name' => $result->student->full_name ?? $result->student->firstname.' '.$result->student->lastname,
                    ] : null,
                    'average' => $result->average,
                    'mention' => $result->mention,
                    'rank' => $result->rank,
                    'total_ranked' => $result->total_ranked,
                    'total_credits' => $result->total_credits,
                    'acquired_credits' => $result->acquired_credits,
                    'success_rate' => $result->success_rate,
                    'final_status' => $result->final_status,
                    'final_status_label' => $result->final_status_label,
                    'final_status_color' => $result->final_status_color,
                    'can_progress_next_year' => $result->can_progress_next_year,
                    'is_final_published' => $result->is_final_published,
                    'final_published_at' => $result->final_published_at?->toIso8601String(),
                    'attestation_available' => $result->attestation_file_path !== null,
                ];
            }),
            'meta' => [
                'total' => $results->count(),
            ],
        ]);
    }

    /**
     * Lock academic year for a semester
     * POST /api/admin/semesters/{semester}/lock-year
     */
    public function lockYear(int $semesterId): JsonResponse
    {
        $result = $this->finalResultsService->lockAcademicYear($semesterId);

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
     * Check if semester is locked
     * GET /api/admin/semesters/{semester}/is-locked
     */
    public function isLocked(int $semesterId): JsonResponse
    {
        $isLocked = $this->finalResultsService->isSemesterLocked($semesterId);

        $lockedAt = null;
        if ($isLocked) {
            $result = SemesterResult::where('semester_id', $semesterId)
                ->whereNotNull('year_locked_at')
                ->first();
            $lockedAt = $result?->year_locked_at;
        }

        return response()->json([
            'data' => [
                'is_locked' => $isLocked,
                'locked_at' => $lockedAt?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get student's final result
     * GET /api/admin/students/{student}/semesters/{semester}/final-result
     */
    public function studentResult(int $studentId, int $semesterId): JsonResponse
    {
        $result = $this->finalResultsService->getStudentFinalResult($studentId, $semesterId);

        if (! $result) {
            return response()->json([
                'message' => 'Résultat non trouvé.',
            ], 404);
        }

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Get student's debts (failed modules)
     * GET /api/admin/students/{student}/semesters/{semester}/debts
     */
    public function studentDebts(int $studentId, int $semesterId): JsonResponse
    {
        $debts = $this->finalResultsService->getStudentDebts($studentId, $semesterId);

        return response()->json([
            'data' => $debts->map(fn ($mg) => [
                'module_id' => $mg->module_id,
                'module' => $mg->module ? [
                    'code' => $mg->module->code,
                    'name' => $mg->module->name,
                    'credits_ects' => $mg->module->credits_ects,
                ] : null,
                'average' => $mg->average,
                'status' => $mg->status,
                'has_retake_grade' => $mg->has_retake_grade,
            ]),
            'meta' => [
                'total' => $debts->count(),
                'total_missing_credits' => $debts->sum(fn ($mg) => $mg->module?->credits_ects ?? 0),
            ],
        ]);
    }

    /**
     * Download student attestation
     * GET /api/admin/students/{student}/semesters/{semester}/attestation
     */
    public function downloadAttestation(int $studentId, int $semesterId): JsonResponse
    {
        $result = SemesterResult::where([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
        ])->first();

        if (! $result || ! $result->attestation_file_path) {
            return response()->json([
                'message' => 'Attestation non disponible.',
            ], 404);
        }

        // Return attestation info (actual download would use Storage)
        return response()->json([
            'data' => [
                'path' => $result->attestation_file_path,
                'available' => true,
                'student_id' => $studentId,
                'semester_id' => $semesterId,
            ],
        ]);
    }
}
