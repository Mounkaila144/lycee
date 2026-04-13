<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Modules\NotesEvaluations\Entities\DeliberationSession;
use Modules\NotesEvaluations\Entities\JuryDecision;
use Modules\NotesEvaluations\Entities\PVGenerationLog;
use Modules\NotesEvaluations\Entities\SemesterResult;
use Modules\StructureAcademique\Entities\Semester;

class DeliberationReportService
{
    /**
     * Generate PV de délibération data for a session
     */
    public function generatePVData(DeliberationSession $session): array
    {
        $session->load(['semester.academicYear', 'juryMembers.user']);

        $semester = $session->semester;
        $decisions = JuryDecision::where('deliberation_session_id', $session->id)
            ->with(['student', 'semesterResult'])
            ->get();

        // Statistics
        $stats = $this->calculateStatistics($decisions);

        // Results sorted alphabetically
        $results = $decisions->sortBy(function ($decision) {
            return $decision->student?->lastname.' '.$decision->student?->firstname;
        })->values();

        // Special cases
        $specialCases = $decisions->whereIn('decision_type', [
            'exceptional_admission',
            'exceptional_refusal',
            'mention_upgrade',
            'deferred',
        ]);

        // Jury members
        $president = $session->juryMembers->firstWhere('role', 'president');
        $secretary = $session->juryMembers->firstWhere('role', 'secretary');
        $members = $session->juryMembers->whereNotIn('role', ['president', 'secretary']);

        return [
            'session' => [
                'id' => $session->id,
                'type' => $session->type,
                'type_label' => $this->getSessionTypeLabel($session->type),
                'session_date' => $session->session_date?->format('d F Y à H:i'),
                'location' => $session->location,
                'status' => $session->status,
            ],
            'semester' => [
                'id' => $semester?->id,
                'name' => $semester?->name,
                'code' => $semester?->code,
                'academic_year' => $semester?->academicYear?->name,
            ],
            'jury' => [
                'president' => $president ? [
                    'id' => $president->user_id,
                    'name' => $president->user?->full_name ?? $president->user?->name,
                ] : null,
                'secretary' => $secretary ? [
                    'id' => $secretary->user_id,
                    'name' => $secretary->user?->full_name ?? $secretary->user?->name,
                ] : null,
                'members' => $members->map(fn ($m) => [
                    'id' => $m->user_id,
                    'name' => $m->user?->full_name ?? $m->user?->name,
                    'role' => $m->role,
                ]),
            ],
            'statistics' => $stats,
            'results' => $results->map(fn ($d, $index) => $this->formatDecisionForPV($d, $index + 1)),
            'special_cases' => $specialCases->values()->map(fn ($d, $index) => [
                'number' => $index + 1,
                'student' => [
                    'matricule' => $d->student?->matricule,
                    'full_name' => $d->student?->full_name,
                ],
                'decision_type' => $d->decision_type,
                'decision_label' => $d->decision_label,
                'comment' => $d->comment,
            ]),
            'generated_at' => now()->format('d/m/Y à H:i'),
        ];
    }

    /**
     * Generate and store PV PDF
     */
    public function generateAndStorePV(DeliberationSession $session): PVGenerationLog
    {
        $pvData = $this->generatePVData($session);

        $semester = $session->semester;
        $filename = sprintf(
            'PV-Deliberation-%s-%s-%s.json',
            $semester?->code ?? $session->semester_id,
            $session->type,
            $session->session_date?->format('Y-m-d') ?? now()->format('Y-m-d')
        );

        $path = sprintf(
            'deliberations/%s/%s',
            $semester?->academicYear?->name ?? 'unknown',
            $filename
        );

        // Store PV data as JSON (PDF generation would use a PDF library in production)
        Storage::disk('tenant')->put(
            $path,
            json_encode($pvData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        // Create generation log
        return PVGenerationLog::create([
            'deliberation_session_id' => $session->id,
            'semester_id' => $session->semester_id,
            'file_path' => $path,
            'file_name' => $filename,
            'type' => $session->type,
            'generated_by' => auth()->id(),
            'generated_at' => now(),
            'statistics' => $pvData['statistics'],
            'metadata' => [
                'jury_president' => $pvData['jury']['president']['name'] ?? null,
                'total_students' => $pvData['statistics']['total'],
                'session_date' => $session->session_date?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Search PV generation logs
     */
    public function searchPVs(array $filters): Collection
    {
        $query = PVGenerationLog::with(['session', 'semester', 'generator']);

        if (! empty($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['academic_year_id'])) {
            $query->whereHas('semester', fn ($q) => $q->where('academic_year_id', $filters['academic_year_id'])
            );
        }

        if (! empty($filters['from_date'])) {
            $query->where('generated_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->where('generated_at', '<=', $filters['to_date']);
        }

        return $query->orderByDesc('generated_at')->get();
    }

    /**
     * Get PV file contents
     */
    public function getPVContents(PVGenerationLog $log): ?array
    {
        if (! Storage::disk('tenant')->exists($log->file_path)) {
            return null;
        }

        $contents = Storage::disk('tenant')->get($log->file_path);

        return json_decode($contents, true);
    }

    /**
     * Get PV history for a semester
     */
    public function getSemesterPVHistory(int $semesterId): Collection
    {
        return PVGenerationLog::where('semester_id', $semesterId)
            ->with(['session', 'generator'])
            ->orderByDesc('generated_at')
            ->get();
    }

    /**
     * Get latest PV for a session
     */
    public function getLatestPV(int $sessionId): ?PVGenerationLog
    {
        return PVGenerationLog::where('deliberation_session_id', $sessionId)
            ->latest('generated_at')
            ->first();
    }

    /**
     * Regenerate PV for a session
     */
    public function regeneratePV(DeliberationSession $session): PVGenerationLog
    {
        // Delete old file if exists
        $oldPV = $this->getLatestPV($session->id);

        if ($oldPV && Storage::disk('tenant')->exists($oldPV->file_path)) {
            Storage::disk('tenant')->delete($oldPV->file_path);
        }

        return $this->generateAndStorePV($session);
    }

    /**
     * Generate summary report for multiple semesters
     */
    public function generateSummaryReport(int $academicYearId): array
    {
        $semesters = Semester::where('academic_year_id', $academicYearId)
            ->with('academicYear')
            ->get();

        $summaries = [];

        foreach ($semesters as $semester) {
            $results = SemesterResult::where('semester_id', $semester->id)->get();

            $summaries[] = [
                'semester' => [
                    'id' => $semester->id,
                    'name' => $semester->name,
                ],
                'statistics' => [
                    'total' => $results->count(),
                    'admitted' => $results->where('final_status', 'admitted')->count(),
                    'admitted_with_debts' => $results->where('final_status', 'admitted_with_debts')->count(),
                    'deferred' => $results->where('final_status', 'deferred_final')->count(),
                    'repeating' => $results->where('final_status', 'repeating')->count(),
                    'success_rate' => $results->count() > 0
                        ? round(($results->whereIn('final_status', ['admitted', 'admitted_with_debts'])->count() / $results->count()) * 100, 2)
                        : 0,
                    'class_average' => $results->avg('average') ? round($results->avg('average'), 2) : null,
                ],
            ];
        }

        return [
            'academic_year' => $semesters->first()?->academicYear?->name,
            'semesters' => $summaries,
            'generated_at' => now()->format('d/m/Y à H:i'),
        ];
    }

    /**
     * Calculate statistics from decisions
     */
    protected function calculateStatistics(Collection $decisions): array
    {
        $total = $decisions->count();

        return [
            'total' => $total,
            'admitted' => $decisions->where('decision_type', 'validated')->count(),
            'admitted_with_debts' => $decisions->where('decision_type', 'admitted_with_debts')->count(),
            'to_retake' => $decisions->where('decision_type', 'to_retake')->count(),
            'deferred' => $decisions->where('decision_type', 'deferred')->count(),
            'exceptional' => $decisions->whereIn('decision_type', [
                'exceptional_admission',
                'exceptional_refusal',
            ])->count(),
            'rates' => [
                'success_rate' => $total > 0
                    ? round(($decisions->whereIn('decision_type', ['validated', 'admitted_with_debts'])->count() / $total) * 100, 2)
                    : 0,
                'retake_rate' => $total > 0
                    ? round(($decisions->where('decision_type', 'to_retake')->count() / $total) * 100, 2)
                    : 0,
            ],
        ];
    }

    /**
     * Format decision for PV display
     */
    protected function formatDecisionForPV(JuryDecision $decision, int $number): array
    {
        $result = $decision->semesterResult;

        return [
            'number' => $number,
            'student' => [
                'matricule' => $decision->student?->matricule,
                'lastname' => $decision->student?->lastname,
                'firstname' => $decision->student?->firstname,
            ],
            'average' => $result?->average ? number_format($result->average, 2) : 'N/A',
            'ects' => [
                'acquired' => $result?->acquired_credits ?? 0,
                'total' => $result?->total_credits ?? 0,
            ],
            'decision_type' => $decision->decision_type,
            'decision_label' => $decision->decision_label,
            'mention' => $result?->mention ?? '-',
            'has_observation' => ! empty($decision->comment),
        ];
    }

    /**
     * Get session type label
     */
    protected function getSessionTypeLabel(string $type): string
    {
        return match ($type) {
            'session1' => 'Session 1',
            'rattrapage' => 'Session de Rattrapage',
            'final' => 'Délibération Finale',
            default => 'Délibération',
        };
    }
}
