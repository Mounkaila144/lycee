<?php

namespace Modules\Attendance\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Attendance\Entities\AbsenceJustification;
use Modules\Attendance\Entities\AttendanceRecord;

class JustificationService
{
    /**
     * Soumettre un justificatif (Story 05)
     */
    public function submitJustification(
        int $studentId,
        Carbon $dateFrom,
        Carbon $dateTo,
        string $type,
        string $reason,
        ?UploadedFile $document,
        int $submittedBy
    ): AbsenceJustification {
        $documentPath = null;

        if ($document) {
            $documentPath = $document->store('justifications', 'tenant');
        }

        return AbsenceJustification::create([
            'student_id' => $studentId,
            'absence_date_from' => $dateFrom->toDateString(),
            'absence_date_to' => $dateTo->toDateString(),
            'type' => $type,
            'reason' => $reason,
            'document_path' => $documentPath,
            'status' => 'pending',
            'submitted_by' => $submittedBy,
        ]);
    }

    /**
     * Valider / refuser un justificatif (Story 06)
     */
    public function validateJustification(
        AbsenceJustification $justification,
        string $decision,
        ?string $notes,
        int $validatorId
    ): AbsenceJustification {
        return DB::connection('tenant')->transaction(function () use ($justification, $decision, $notes, $validatorId) {
            $justification->update([
                'status' => $decision,
                'validated_by' => $validatorId,
                'validated_at' => now(),
                'validation_notes' => $notes,
            ]);

            // Si approuvé, mettre à jour les absences correspondantes (Story 07)
            if ($decision === 'approved') {
                $this->applyJustificationToRecords($justification, $validatorId);
            }

            return $justification;
        });
    }

    /**
     * Appliquer justificatif aux enregistrements (Story 07)
     */
    private function applyJustificationToRecords(AbsenceJustification $justification, int $validatorId): void
    {
        AttendanceRecord::whereHas('session', function ($query) use ($justification) {
            $query->whereBetween('session_date', [
                $justification->absence_date_from->toDateString(),
                $justification->absence_date_to->toDateString(),
            ]);
        })
            ->where('student_id', $justification->student_id)
            ->where('status', 'absent')
            ->update([
                'status' => 'excused',
                'modified_by' => $validatorId,
                'modification_reason' => 'Justificatif approuvé #'.$justification->id,
            ]);
    }

    /**
     * Obtenir justificatifs en attente (Story 06)
     *
     * @param  list<int>|null  $studentIds  null = tous les élèves
     */
    public function getPendingJustifications(?array $studentIds = null): Collection
    {
        return AbsenceJustification::with(['student', 'submitter'])
            ->pending()
            ->when($studentIds !== null, fn ($query) => $query->whereIn('student_id', $studentIds))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Obtenir justificatifs d'un étudiant
     */
    public function getStudentJustifications(int $studentId): Collection
    {
        return AbsenceJustification::with(['submitter', 'validator'])
            ->forStudent($studentId)
            ->orderBy('absence_date_from', 'desc')
            ->get();
    }

    /**
     * Télécharger document justificatif
     */
    public function downloadDocument(AbsenceJustification $justification): ?string
    {
        if (! $justification->document_path) {
            return null;
        }

        return Storage::disk('tenant')->path($justification->document_path);
    }
}
