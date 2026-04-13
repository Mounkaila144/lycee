<?php

namespace Modules\NotesEvaluations\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\NotesEvaluations\Entities\DeliberationSession;
use Modules\NotesEvaluations\Entities\JuryDecision;
use Modules\NotesEvaluations\Http\Requests\CreateDeliberationSessionRequest;
use Modules\NotesEvaluations\Http\Requests\RecordDecisionRequest;
use Modules\NotesEvaluations\Http\Resources\SemesterResultResource;
use Modules\NotesEvaluations\Services\DeliberationService;

class DeliberationController extends Controller
{
    public function __construct(
        private DeliberationService $deliberationService
    ) {}

    /**
     * List all deliberation sessions
     */
    public function index(Request $request): JsonResponse
    {
        $sessions = DeliberationSession::with(['semester.academicYear', 'programme', 'president'])
            ->when($request->semester_id, fn ($q, $id) => $q->where('semester_id', $id))
            ->when($request->programme_id, fn ($q, $id) => $q->where('programme_id', $id))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->session_type, fn ($q, $type) => $q->where('session_type', $type))
            ->orderBy('scheduled_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'data' => $sessions->map(fn ($s) => $this->formatSession($s)),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    /**
     * Create a new deliberation session
     */
    public function store(CreateDeliberationSessionRequest $request): JsonResponse
    {
        $session = $this->deliberationService->createSession($request->validated());

        return response()->json([
            'message' => 'Session de délibération créée avec succès.',
            'data' => $this->formatSession($session->load(['semester', 'programme', 'president'])),
        ], 201);
    }

    /**
     * Show a deliberation session
     */
    public function show(DeliberationSession $session): JsonResponse
    {
        $session->load(['semester.academicYear', 'programme', 'president', 'creator']);

        $statistics = $this->deliberationService->getSessionStatistics($session);

        return response()->json([
            'data' => array_merge($this->formatSession($session), [
                'statistics' => $statistics,
            ]),
        ]);
    }

    /**
     * Start a deliberation session
     */
    public function start(DeliberationSession $session): JsonResponse
    {
        if (! $this->deliberationService->startSession($session)) {
            return response()->json([
                'message' => 'Impossible de démarrer cette session. Vérifiez le statut et la date prévue.',
            ], 400);
        }

        return response()->json([
            'message' => 'Session de délibération démarrée.',
            'data' => $this->formatSession($session->fresh()),
        ]);
    }

    /**
     * Get students pending deliberation
     */
    public function pendingStudents(DeliberationSession $session): JsonResponse
    {
        $students = $this->deliberationService->getStudentsPendingDeliberation($session);

        return response()->json([
            'data' => $students->map(function ($result) {
                $suggestion = $this->deliberationService->suggestDecision($result);

                return [
                    'semester_result' => new SemesterResultResource($result),
                    'suggestion' => $suggestion,
                ];
            }),
            'count' => $students->count(),
        ]);
    }

    /**
     * Get deliberated students
     */
    public function deliberatedStudents(DeliberationSession $session): JsonResponse
    {
        $decisions = $this->deliberationService->getDeliberatedStudents($session);

        return response()->json([
            'data' => $decisions->map(fn ($d) => $this->formatDecision($d)),
            'count' => $decisions->count(),
        ]);
    }

    /**
     * Record a decision for a student
     */
    public function recordDecision(RecordDecisionRequest $request, DeliberationSession $session): JsonResponse
    {
        try {
            $decision = $this->deliberationService->recordDecision(
                $session,
                $request->student_id,
                $request->decision,
                $request->only([
                    'justification',
                    'conditions',
                    'is_exceptional',
                    'exceptional_reason',
                    'votes_for',
                    'votes_against',
                    'abstentions',
                ])
            );

            return response()->json([
                'message' => 'Décision enregistrée avec succès.',
                'data' => $this->formatDecision($decision->load(['student', 'semesterResult'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Record bulk decisions
     */
    public function recordBulkDecisions(Request $request, DeliberationSession $session): JsonResponse
    {
        $request->validate([
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*.student_id' => ['required', 'integer', 'exists:tenant.students,id'],
            'decisions.*.decision' => ['required', 'string', 'in:validated,compensated,retake,repeat_year,exclusion,conditional,deferred'],
            'decisions.*.options' => ['sometimes', 'array'],
        ]);

        try {
            $result = $this->deliberationService->recordBulkDecisions(
                $session,
                $request->decisions
            );

            return response()->json([
                'message' => sprintf(
                    '%d décision(s) enregistrée(s), %d erreur(s).',
                    count($result['recorded']),
                    count($result['errors'])
                ),
                'data' => [
                    'recorded_count' => count($result['recorded']),
                    'errors' => $result['errors'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Complete a deliberation session
     */
    public function complete(Request $request, DeliberationSession $session): JsonResponse
    {
        $request->validate([
            'minutes' => ['sometimes', 'string'],
        ]);

        try {
            $summary = $this->deliberationService->completeSession(
                $session,
                $request->minutes
            );

            return response()->json([
                'message' => 'Session de délibération terminée.',
                'data' => [
                    'session' => $this->formatSession($session->fresh()),
                    'summary' => $summary,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel a deliberation session
     */
    public function cancel(DeliberationSession $session): JsonResponse
    {
        if ($session->status === 'completed') {
            return response()->json([
                'message' => 'Impossible d\'annuler une session terminée.',
            ], 400);
        }

        $session->cancel();

        return response()->json([
            'message' => 'Session de délibération annulée.',
        ]);
    }

    /**
     * Get decisions requiring review
     */
    public function decisionsRequiringReview(Request $request): JsonResponse
    {
        $decisions = $this->deliberationService->getDecisionsRequiringReview(
            $request->session_id
        );

        return response()->json([
            'data' => $decisions->map(fn ($d) => $this->formatDecision($d)),
            'count' => $decisions->count(),
        ]);
    }

    /**
     * Review a decision
     */
    public function reviewDecision(Request $request, JuryDecision $decision): JsonResponse
    {
        $request->validate([
            'approve' => ['required', 'boolean'],
            'note' => ['sometimes', 'string'],
        ]);

        $this->deliberationService->reviewDecision(
            $decision,
            $request->approve,
            $request->note
        );

        return response()->json([
            'message' => 'Décision révisée avec succès.',
            'data' => $this->formatDecision($decision->fresh(['student', 'semesterResult', 'reviewedByUser'])),
        ]);
    }

    /**
     * Get student deliberation history
     */
    public function studentHistory(int $studentId): JsonResponse
    {
        $history = $this->deliberationService->getStudentHistory($studentId);

        return response()->json([
            'data' => $history->map(fn ($d) => $this->formatDecision($d)),
        ]);
    }

    /**
     * Format session for response
     */
    private function formatSession(DeliberationSession $session): array
    {
        return [
            'id' => $session->id,
            'semester_id' => $session->semester_id,
            'programme_id' => $session->programme_id,
            'session_type' => $session->session_type,
            'session_type_label' => $session->session_type_label,
            'status' => $session->status,
            'status_label' => $session->status_label,
            'scheduled_at' => $session->scheduled_at?->toIso8601String(),
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'duration_minutes' => $session->duration,
            'location' => $session->location,
            'agenda' => $session->agenda,
            'jury_members' => $session->jury_members,
            'minutes' => $session->minutes,
            'summary' => $session->summary,
            'decisions_count' => $session->decisions_count,
            'semester' => $session->relationLoaded('semester') ? [
                'id' => $session->semester->id,
                'name' => $session->semester->name,
                'academic_year' => $session->semester->academicYear?->name,
            ] : null,
            'programme' => $session->relationLoaded('programme') && $session->programme ? [
                'id' => $session->programme->id,
                'name' => $session->programme->name,
                'code' => $session->programme->code,
            ] : null,
            'president' => $session->relationLoaded('president') && $session->president ? [
                'id' => $session->president->id,
                'name' => $session->president->name,
            ] : null,
            'created_at' => $session->created_at?->toIso8601String(),
        ];
    }

    /**
     * Format decision for response
     */
    private function formatDecision(JuryDecision $decision): array
    {
        return [
            'id' => $decision->id,
            'student_id' => $decision->student_id,
            'decision' => $decision->decision,
            'decision_label' => $decision->decision_label,
            'decision_color' => $decision->decision_color,
            'is_positive' => $decision->is_positive_decision,
            'average_at_decision' => $decision->average_at_decision,
            'acquired_credits_at_decision' => $decision->acquired_credits_at_decision,
            'missing_credits_at_decision' => $decision->missing_credits_at_decision,
            'justification' => $decision->justification,
            'conditions' => $decision->conditions,
            'is_exceptional' => $decision->is_exceptional,
            'exceptional_reason' => $decision->exceptional_reason,
            'vote_summary' => $decision->vote_summary,
            'requires_review' => $decision->requires_review,
            'is_reviewed' => $decision->is_reviewed,
            'decided_at' => $decision->decided_at?->toIso8601String(),
            'reviewed_at' => $decision->reviewed_at?->toIso8601String(),
            'student' => $decision->relationLoaded('student') ? [
                'id' => $decision->student->id,
                'matricule' => $decision->student->matricule,
                'full_name' => $decision->student->full_name ?? $decision->student->firstname.' '.$decision->student->lastname,
            ] : null,
            'semester_result' => $decision->relationLoaded('semesterResult')
                ? new SemesterResultResource($decision->semesterResult)
                : null,
            'decided_by' => $decision->relationLoaded('decidedByUser') && $decision->decidedByUser ? [
                'id' => $decision->decidedByUser->id,
                'name' => $decision->decidedByUser->name,
            ] : null,
            'reviewed_by' => $decision->relationLoaded('reviewedByUser') && $decision->reviewedByUser ? [
                'id' => $decision->reviewedByUser->id,
                'name' => $decision->reviewedByUser->name,
            ] : null,
        ];
    }
}
