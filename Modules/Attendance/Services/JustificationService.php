<?php

namespace Modules\Attendance\Services;

use Carbon\Carbon;
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
        $document = null
    ): AbsenceJustification {
        $documentPath = null;

        if ($document) {
            $documentPath = $document->store('justifications', 'tenant');
        }

        return AbsenceJustification::create([
            'student_id' => $studentId,
            'absence_date_from' => $dateFrom,
            'absence_date_to' => $dateTo,
            'type' => $type,
            'reason' => $reason,
            'document_path' => $documentPath,
            'status' => 'pending',
            'submitted_by' => auth()->id(),
        ]);
    }

    /**
     * Valider un justificatif (Story 06)
     */
    public function validateJustification(
        int $justificationId,
        string $decision,
        ?string $notes = null
    ): AbsenceJustification {
        $justification = AbsenceJustification::findOrFail($justificationId);

        return DB::connection('tenant')->transaction(function () use ($justification, $decision, $notes) {
            $justification->update([
                'status' => $decision,
                'validated_by' => auth()->id(),
                'validated_at' => now(),
                'validation_notes' => $notes,
            ]);

            // Si approuvé, mettre à jour les absences correspondantes (Story 07)
            if ($decision === 'approved') {
                $this->applyJustificationToRecords($justification);
            }

            return $justification;
        });
    }

    /**
     * Appliquer justificatif aux enregistrements (Story 07)
     */
    private function applyJustificationToRecords(AbsenceJustification $justification): void
    {
        AttendanceRecord::whereHas('session', function ($query) use ($justification) {
            $query->whereBetween('session_date', [
                $justification->absence_date_from,
                $justification->absence_date_to,
            ]);
        })
            ->where('student_id', $justification->student_id)
            ->where('status', 'absent')
            ->update([
            'status' => 'excused',
            'modified_by' => auth()->id(),
            'modification_reason' => 'Justificatif approuvé #'.$justification->id,
        ]);
    }

    /**
     * Obtenir justificatifs en attente (Story 06)
     */
    public function getPendingJustifications(): Collection
    {
        return AbsenceJustification::with(['student', 'submitter'])
            ->pending()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Obtenir justificatifs d'un étudiant
     */
    public function getStudentJustifications(int $studentId): Collection
    {
        return AbsenceJustification::with(['validator'])
            ->forStudent($studentId)
            ->orderBy('absence_date_from', 'desc')
            ->get();
    }

    /**
     * Télécharger document justificatif
     */
    public function downloadDocument(int $justificationId): ?string
    {
        $justification = AbsenceJustification::findOrFail($justificationId);

        if (! $justification->document_path) {
            return null;
        }

        return Storage::disk('tenant')->path($justification->document_path);
    }
}
