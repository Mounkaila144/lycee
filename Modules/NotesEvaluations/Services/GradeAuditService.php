<?php

namespace Modules\NotesEvaluations\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\NotesEvaluations\Entities\GradeCorrectionRequest;
use Modules\NotesEvaluations\Entities\GradeHistory;
use Modules\NotesEvaluations\Exports\GradeAuditExport;
use Modules\NotesEvaluations\Notifications\CorrectionRequestNotification;
use Modules\UsersGuard\Entities\TenantUser;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GradeAuditService
{
    /**
     * Get history for a specific grade
     */
    public function getGradeHistory(Grade $grade): Collection
    {
        return GradeHistory::where('grade_id', $grade->id)
            ->with('changedBy:id,firstname,lastname,email')
            ->orderByDesc('changed_at')
            ->get();
    }

    /**
     * Request a correction for a published grade
     */
    public function requestCorrection(
        Grade $grade,
        Authenticatable $teacher,
        ?float $proposedValue,
        bool $proposedIsAbsent,
        string $reason
    ): GradeCorrectionRequest {
        if (! $grade->requiresCorrectionRequest()) {
            throw new \Exception('Cette note ne nécessite pas de demande de correction.');
        }

        // Check for pending request
        $existingRequest = GradeCorrectionRequest::where('grade_id', $grade->id)
            ->pending()
            ->first();

        if ($existingRequest) {
            throw new \Exception('Une demande de correction est déjà en attente pour cette note.');
        }

        $request = GradeCorrectionRequest::create([
            'grade_id' => $grade->id,
            'requested_by' => $teacher->id,
            'current_value' => $grade->score,
            'proposed_value' => $proposedValue,
            'current_is_absent' => $grade->is_absent,
            'proposed_is_absent' => $proposedIsAbsent,
            'reason' => $reason,
            'status' => 'Pending',
        ]);

        // Notify administrators
        $this->notifyAdminsOfCorrectionRequest($request);

        return $request;
    }

    /**
     * Approve a correction request
     */
    public function approveCorrection(
        GradeCorrectionRequest $request,
        Authenticatable $reviewer,
        ?string $comment = null
    ): void {
        if (! $request->isPending()) {
            throw new \Exception('Cette demande ne peut plus être traitée.');
        }

        $request->approve($reviewer, $comment);

        // Notify teacher
        $request->requester->notify(new CorrectionRequestNotification($request, 'approved'));
    }

    /**
     * Reject a correction request
     */
    public function rejectCorrection(
        GradeCorrectionRequest $request,
        Authenticatable $reviewer,
        string $comment
    ): void {
        if (! $request->isPending()) {
            throw new \Exception('Cette demande ne peut plus être traitée.');
        }

        $request->reject($reviewer, $comment);

        // Notify teacher
        $request->requester->notify(new CorrectionRequestNotification($request, 'rejected'));
    }

    /**
     * Apply an approved correction
     */
    public function applyCorrection(GradeCorrectionRequest $request, Authenticatable $teacher): void
    {
        if (! $request->isActive()) {
            throw new \Exception('Cette demande de correction a expiré ou n\'est pas active.');
        }

        $grade = $request->grade;

        $grade->update([
            'score' => $request->proposed_value,
            'is_absent' => $request->proposed_is_absent,
            'entered_by' => $teacher->id,
            'entered_at' => now(),
        ]);
    }

    /**
     * Get pending correction requests
     */
    public function getPendingRequests(): Collection
    {
        return GradeCorrectionRequest::with(['grade.student', 'grade.evaluation.module', 'requester'])
            ->pending()
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Export module audit trail to Excel
     */
    public function exportModuleAuditTrail(int $moduleId, string $filename = 'audit_trail.xlsx'): BinaryFileResponse
    {
        return Excel::download(new GradeAuditExport($moduleId), $filename);
    }

    /**
     * Get audit statistics for a module
     */
    public function getAuditStatistics(int $moduleId): array
    {
        $grades = Grade::whereHas('evaluation', function ($q) use ($moduleId) {
            $q->where('module_id', $moduleId);
        })->pluck('id');

        $history = GradeHistory::whereIn('grade_id', $grades);

        return [
            'total_changes' => $history->count(),
            'creations' => (clone $history)->where('change_type', 'creation')->count(),
            'modifications' => (clone $history)->where('change_type', 'modification')->count(),
            'corrections' => (clone $history)->where('change_type', 'correction')->count(),
            'correction_requests' => [
                'total' => GradeCorrectionRequest::whereIn('grade_id', $grades)->count(),
                'pending' => GradeCorrectionRequest::whereIn('grade_id', $grades)->pending()->count(),
                'approved' => GradeCorrectionRequest::whereIn('grade_id', $grades)->approved()->count(),
                'rejected' => GradeCorrectionRequest::whereIn('grade_id', $grades)->rejected()->count(),
            ],
        ];
    }

    /**
     * Notify admins about new correction request
     */
    private function notifyAdminsOfCorrectionRequest(GradeCorrectionRequest $request): void
    {
        $admins = TenantUser::role('Administrator')->get();

        foreach ($admins as $admin) {
            $admin->notify(new CorrectionRequestNotification($request, 'new'));
        }
    }
}
