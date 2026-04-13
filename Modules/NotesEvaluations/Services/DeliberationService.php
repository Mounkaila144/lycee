<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\NotesEvaluations\Entities\DeliberationSession;
use Modules\NotesEvaluations\Entities\JuryDecision;
use Modules\NotesEvaluations\Entities\SemesterResult;

class DeliberationService
{
    /**
     * Create a new deliberation session
     */
    public function createSession(array $data): DeliberationSession
    {
        return DeliberationSession::create([
            'semester_id' => $data['semester_id'],
            'programme_id' => $data['programme_id'] ?? null,
            'session_type' => $data['session_type'] ?? 'regular',
            'status' => 'pending',
            'scheduled_at' => $data['scheduled_at'],
            'location' => $data['location'] ?? null,
            'agenda' => $data['agenda'] ?? null,
            'jury_members' => $data['jury_members'] ?? [],
            'president_id' => $data['president_id'] ?? null,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Start a deliberation session
     */
    public function startSession(DeliberationSession $session): bool
    {
        if (! $session->canStart()) {
            return false;
        }

        $session->start();

        Log::info('Deliberation session started', [
            'session_id' => $session->id,
            'semester_id' => $session->semester_id,
        ]);

        return true;
    }

    /**
     * Get students pending deliberation for a session
     */
    public function getStudentsPendingDeliberation(DeliberationSession $session): Collection
    {
        $query = SemesterResult::where('semester_id', $session->semester_id)
            ->with(['student', 'ectsAllocations'])
            ->whereDoesntHave('juryDecisions', function ($q) use ($session) {
                $q->where('deliberation_session_id', $session->id);
            });

        // Filter by programme if specified
        if ($session->programme_id) {
            $query->whereHas('student.enrollments', function ($q) use ($session) {
                $q->where('programme_id', $session->programme_id);
            });
        }

        return $query->orderBy('average', 'desc')->get();
    }

    /**
     * Get students already deliberated in a session
     */
    public function getDeliberatedStudents(DeliberationSession $session): Collection
    {
        return JuryDecision::where('deliberation_session_id', $session->id)
            ->with(['student', 'semesterResult', 'decidedByUser'])
            ->orderBy('decided_at', 'desc')
            ->get();
    }

    /**
     * Record a jury decision for a student
     */
    public function recordDecision(
        DeliberationSession $session,
        int $studentId,
        string $decision,
        array $options = []
    ): JuryDecision {
        if (! $session->canAddDecisions()) {
            throw new \Exception('Cette session de délibération n\'accepte plus de décisions.');
        }

        $semesterResult = SemesterResult::where('student_id', $studentId)
            ->where('semester_id', $session->semester_id)
            ->firstOrFail();

        // Check for existing decision
        $existing = JuryDecision::where('deliberation_session_id', $session->id)
            ->where('student_id', $studentId)
            ->first();

        if ($existing) {
            throw new \Exception('Une décision existe déjà pour cet étudiant dans cette session.');
        }

        $juryDecision = JuryDecision::create([
            'deliberation_session_id' => $session->id,
            'student_id' => $studentId,
            'semester_result_id' => $semesterResult->id,
            'decision' => $decision,
            'average_at_decision' => $semesterResult->average,
            'acquired_credits_at_decision' => $semesterResult->acquired_credits,
            'missing_credits_at_decision' => $semesterResult->missing_credits,
            'justification' => $options['justification'] ?? null,
            'conditions' => $options['conditions'] ?? null,
            'is_exceptional' => $options['is_exceptional'] ?? false,
            'exceptional_reason' => $options['exceptional_reason'] ?? null,
            'votes_for' => $options['votes_for'] ?? null,
            'votes_against' => $options['votes_against'] ?? null,
            'abstentions' => $options['abstentions'] ?? null,
            'decided_by' => Auth::id(),
            'decided_at' => now(),
            'requires_review' => $options['is_exceptional'] ?? false,
        ]);

        // Apply decision to semester result
        $juryDecision->applyToSemesterResult();

        Log::info('Jury decision recorded', [
            'session_id' => $session->id,
            'student_id' => $studentId,
            'decision' => $decision,
        ]);

        return $juryDecision;
    }

    /**
     * Bulk record decisions for multiple students
     */
    public function recordBulkDecisions(
        DeliberationSession $session,
        array $decisions
    ): array {
        $recorded = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($decisions as $item) {
                try {
                    $decision = $this->recordDecision(
                        $session,
                        $item['student_id'],
                        $item['decision'],
                        $item['options'] ?? []
                    );
                    $recorded[] = $decision;
                } catch (\Exception $e) {
                    $errors[] = [
                        'student_id' => $item['student_id'],
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'recorded' => $recorded,
            'errors' => $errors,
        ];
    }

    /**
     * Suggest automatic decision based on rules
     */
    public function suggestDecision(SemesterResult $result): array
    {
        $suggestion = [
            'decision' => 'deferred',
            'reason' => '',
            'confidence' => 'low',
        ];

        // Validated semester
        if ($result->is_validated && $result->failed_modules_count === 0) {
            return [
                'decision' => 'validated',
                'reason' => 'Semestre validé avec tous les modules acquis',
                'confidence' => 'high',
            ];
        }

        // Validated by compensation
        if ($result->is_validated && $result->compensated_modules_count > 0 && $result->failed_modules_count === 0) {
            return [
                'decision' => 'compensated',
                'reason' => sprintf(
                    'Semestre validé par compensation (%d module(s) compensé(s))',
                    $result->compensated_modules_count
                ),
                'confidence' => 'high',
            ];
        }

        // Has failed modules but semester average >= 10
        if ($result->average >= 10 && $result->failed_modules_count > 0) {
            return [
                'decision' => 'retake',
                'reason' => sprintf(
                    'Moyenne suffisante (%.2f) mais %d module(s) non validé(s)',
                    $result->average,
                    $result->failed_modules_count
                ),
                'confidence' => 'medium',
            ];
        }

        // Semester average < 10
        if ($result->average < 10) {
            // Check if close to threshold
            if ($result->average >= 9) {
                return [
                    'decision' => 'retake',
                    'reason' => sprintf(
                        'Moyenne proche du seuil (%.2f)',
                        $result->average
                    ),
                    'confidence' => 'medium',
                ];
            }

            return [
                'decision' => 'repeat_year',
                'reason' => sprintf(
                    'Moyenne insuffisante (%.2f) avec %d crédits manquants',
                    $result->average,
                    $result->missing_credits
                ),
                'confidence' => 'medium',
            ];
        }

        // Blocked by eliminatory
        if ($result->validation_blocked_by_eliminatory) {
            return [
                'decision' => 'retake',
                'reason' => 'Bloqué par module(s) éliminatoire(s)',
                'confidence' => 'high',
            ];
        }

        return $suggestion;
    }

    /**
     * Complete a deliberation session
     */
    public function completeSession(DeliberationSession $session, ?string $minutes = null): array
    {
        if ($session->status !== 'in_progress') {
            throw new \Exception('Cette session n\'est pas en cours.');
        }

        $decisions = $session->decisions;

        // Generate summary statistics
        $summary = [
            'total_students' => $decisions->count(),
            'validated' => $decisions->where('decision', 'validated')->count(),
            'compensated' => $decisions->where('decision', 'compensated')->count(),
            'retake' => $decisions->where('decision', 'retake')->count(),
            'repeat_year' => $decisions->where('decision', 'repeat_year')->count(),
            'exclusion' => $decisions->where('decision', 'exclusion')->count(),
            'conditional' => $decisions->where('decision', 'conditional')->count(),
            'deferred' => $decisions->where('decision', 'deferred')->count(),
            'exceptional_decisions' => $decisions->where('is_exceptional', true)->count(),
        ];

        // Calculate success rate
        $positiveDecisions = $summary['validated'] + $summary['compensated'] + $summary['conditional'];
        $summary['success_rate'] = $summary['total_students'] > 0
            ? round(($positiveDecisions / $summary['total_students']) * 100, 2)
            : 0;

        $session->complete($summary);

        if ($minutes) {
            $session->update(['minutes' => $minutes]);
        }

        Log::info('Deliberation session completed', [
            'session_id' => $session->id,
            'summary' => $summary,
        ]);

        return $summary;
    }

    /**
     * Get session statistics
     */
    public function getSessionStatistics(DeliberationSession $session): array
    {
        $decisions = $session->decisions;

        $stats = [
            'total_decisions' => $decisions->count(),
            'by_decision' => [
                'validated' => $decisions->where('decision', 'validated')->count(),
                'compensated' => $decisions->where('decision', 'compensated')->count(),
                'retake' => $decisions->where('decision', 'retake')->count(),
                'repeat_year' => $decisions->where('decision', 'repeat_year')->count(),
                'exclusion' => $decisions->where('decision', 'exclusion')->count(),
                'conditional' => $decisions->where('decision', 'conditional')->count(),
                'deferred' => $decisions->where('decision', 'deferred')->count(),
            ],
            'exceptional_count' => $decisions->where('is_exceptional', true)->count(),
            'requiring_review' => $decisions->where('requires_review', true)->whereNull('reviewed_at')->count(),
            'average_at_decision' => round($decisions->avg('average_at_decision'), 2),
        ];

        // Pending students count
        $pendingCount = SemesterResult::where('semester_id', $session->semester_id)
            ->whereDoesntHave('juryDecisions', function ($q) use ($session) {
                $q->where('deliberation_session_id', $session->id);
            })
            ->count();

        $stats['pending_students'] = $pendingCount;

        return $stats;
    }

    /**
     * Get decisions requiring review
     */
    public function getDecisionsRequiringReview(?int $sessionId = null): Collection
    {
        $query = JuryDecision::where('requires_review', true)
            ->whereNull('reviewed_at')
            ->with(['student', 'semesterResult', 'deliberationSession', 'decidedByUser']);

        if ($sessionId) {
            $query->where('deliberation_session_id', $sessionId);
        }

        return $query->orderBy('decided_at', 'asc')->get();
    }

    /**
     * Review a decision
     */
    public function reviewDecision(JuryDecision $decision, bool $approve, ?string $note = null): void
    {
        $decision->markAsReviewed(Auth::id());

        if (! $approve && $note) {
            $decision->update([
                'justification' => ($decision->justification ? $decision->justification."\n" : '').
                    "Note de révision: {$note}",
            ]);
        }

        Log::info('Jury decision reviewed', [
            'decision_id' => $decision->id,
            'approved' => $approve,
            'reviewer_id' => Auth::id(),
        ]);
    }

    /**
     * Get student deliberation history
     */
    public function getStudentHistory(int $studentId): Collection
    {
        return JuryDecision::where('student_id', $studentId)
            ->with(['deliberationSession.semester', 'semesterResult', 'decidedByUser'])
            ->orderBy('decided_at', 'desc')
            ->get();
    }
}
