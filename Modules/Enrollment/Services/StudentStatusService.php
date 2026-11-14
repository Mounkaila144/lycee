<?php

namespace Modules\Enrollment\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\Student;
use Modules\Enrollment\Entities\StudentStatusHistory;
use Modules\Enrollment\Events\StudentStatusChanged;
use Modules\Enrollment\Exceptions\InvalidStatusTransitionException;
use Modules\PortailParent\Entities\ParentModel;
use Modules\UsersGuard\Entities\TenantUser;

class StudentStatusService
{
    /**
     * All available statuses.
     */
    public const STATUSES = [
        'Actif',
        'Suspendu',
        'Exclu',
        'Diplômé',
        'Abandon',
        'Transféré',
    ];

    /**
     * Statuts de sortie : l'élève a quitté l'établissement (son compte de connexion est désactivé).
     */
    public const EXIT_STATUSES = [
        'Exclu',
        'Diplômé',
        'Abandon',
        'Transféré',
    ];

    /**
     * Rôles « famille » : un compte qui porte un autre rôle (personnel) n'est jamais désactivé ici.
     */
    private const FAMILY_ROLES = ['Élève', 'Parent'];

    /**
     * Allowed transitions from each status.
     */
    public const ALLOWED_TRANSITIONS = [
        'Actif' => ['Suspendu', 'Exclu', 'Diplômé', 'Abandon', 'Transféré'],
        'Suspendu' => ['Actif', 'Exclu', 'Abandon'],
        // Final statuses - no transitions allowed
        'Exclu' => [],
        'Diplômé' => [],
        'Abandon' => [],
        'Transféré' => [],
    ];

    /**
     * Change the status of a student.
     *
     * @throws InvalidStatusTransitionException
     */
    public function changeStatus(
        Student $student,
        string $newStatus,
        string $reason,
        ?string $effectiveDate = null,
        ?UploadedFile $document = null
    ): StudentStatusHistory {
        $oldStatus = $student->status;

        // Validate the transition
        $this->validateTransition($oldStatus, $newStatus);

        return DB::connection('tenant')->transaction(function () use (
            $student,
            $oldStatus,
            $newStatus,
            $reason,
            $effectiveDate,
            $document
        ) {
            // Store document if provided
            $documentPath = null;
            if ($document) {
                $documentPath = $document->store(
                    "students/{$student->id}/status-docs",
                    'tenant'
                );
            }

            // Create history record
            $history = StudentStatusHistory::on('tenant')->create([
                'student_id' => $student->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
                'effective_date' => $effectiveDate ?? now()->toDateString(),
                'changed_by' => Auth::id() ?? request()?->user()?->id,
                'document_path' => $documentPath,
            ]);

            // Update student status
            $student->update(['status' => $newStatus]);

            if ($this->isExitStatus($newStatus)) {
                $this->deactivateAccountsAfterDeparture($student);
            }

            // Dispatch event for listeners
            event(new StudentStatusChanged($student, $oldStatus, $newStatus, $history));

            return $history;
        });
    }

    public function isExitStatus(string $status): bool
    {
        return in_array($status, self::EXIT_STATUSES, true);
    }

    /**
     * Désactive les comptes de connexion liés à un élève qui a quitté l'établissement
     * (et révoque leurs jetons d'accès) :
     *  - le compte élève ;
     *  - le compte de chaque parent qui n'a plus aucun autre enfant scolarisé (statut hors sortie).
     *    Un parent qui a encore un enfant dans l'établissement garde son accès.
     * Un compte portant un rôle autre qu'Élève/Parent (ex. personnel également parent) n'est pas touché.
     *
     * @return list<int> identifiants des comptes désactivés
     */
    public function deactivateAccountsAfterDeparture(Student $student): array
    {
        $deactivatedUserIds = [];

        if ($student->user_id !== null && $this->deactivateFamilyAccount($student->user)) {
            $deactivatedUserIds[] = (int) $student->user_id;
        }

        $parents = $student->parents()->whereNotNull('parents.user_id')->with('user')->get();

        foreach ($parents as $parent) {
            /** @var ParentModel $parent */
            $hasChildStillEnrolled = $parent->students()
                ->where('students.id', '!=', $student->id)
                ->whereNotIn('students.status', self::EXIT_STATUSES)
                ->exists();

            if (! $hasChildStillEnrolled && $this->deactivateFamilyAccount($parent->user)) {
                $deactivatedUserIds[] = (int) $parent->user_id;
            }
        }

        return $deactivatedUserIds;
    }

    /**
     * Suppression (soft delete) d'un dossier élève : traitée comme une sortie.
     * Les inscriptions en classe actives passent à « Annulé » (l'élève disparaît des
     * effectifs, appels et saisies de notes) et les comptes famille sont désactivés,
     * puis le dossier est supprimé — le tout dans une transaction.
     *
     * @return array{cancelled_enrollments: int, deactivated_user_ids: list<int>}
     */
    public function deleteStudent(Student $student): array
    {
        return DB::connection('tenant')->transaction(function () use ($student): array {
            $cancelledEnrollments = $student->classEnrollments()
                ->where('status', 'Actif')
                ->update([
                    'status' => 'Annulé',
                    'updated_at' => now(),
                ]);

            $deactivatedUserIds = $this->deactivateAccountsAfterDeparture($student);

            $student->delete();

            return [
                'cancelled_enrollments' => $cancelledEnrollments,
                'deactivated_user_ids' => $deactivatedUserIds,
            ];
        });
    }

    private function deactivateFamilyAccount(?TenantUser $user): bool
    {
        if ($user === null || ! $user->is_active) {
            return false;
        }

        $roleNames = $user->getRoleNames()->all();

        if (array_diff($roleNames, self::FAMILY_ROLES) !== []) {
            return false;
        }

        $user->forceFill(['is_active' => false])->save();
        $user->tokens()->delete();

        return true;
    }

    /**
     * Validate if a status transition is allowed.
     *
     * @throws InvalidStatusTransitionException
     */
    public function validateTransition(string $fromStatus, string $toStatus): void
    {
        // Same status is not a valid transition
        if ($fromStatus === $toStatus) {
            throw new InvalidStatusTransitionException(
                $fromStatus,
                $toStatus,
                "Le statut est déjà '{$toStatus}'"
            );
        }

        // Check if the transition is allowed
        $allowedTransitions = self::ALLOWED_TRANSITIONS[$fromStatus] ?? [];

        if (! in_array($toStatus, $allowedTransitions)) {
            throw new InvalidStatusTransitionException($fromStatus, $toStatus);
        }
    }

    /**
     * Check if a transition is valid without throwing exception.
     */
    public function isTransitionAllowed(string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === $toStatus) {
            return false;
        }

        $allowedTransitions = self::ALLOWED_TRANSITIONS[$fromStatus] ?? [];

        return in_array($toStatus, $allowedTransitions);
    }

    /**
     * Get all allowed transitions from a given status.
     */
    public function getAllowedTransitions(string $fromStatus): array
    {
        return self::ALLOWED_TRANSITIONS[$fromStatus] ?? [];
    }

    /**
     * Get status history for a student.
     */
    public function getStatusHistory(int $studentId, ?int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return StudentStatusHistory::on('tenant')
            ->forStudent($studentId)
            ->with('changedByUser:id,firstname,lastname,email')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get status statistics for all students.
     */
    public function getStatusStatistics(): array
    {
        $stats = Student::on('tenant')
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Ensure all statuses are present
        $result = [];
        foreach (self::STATUSES as $status) {
            $result[$status] = $stats[$status] ?? 0;
        }

        $total = array_sum($result);

        return [
            'by_status' => $result,
            'total' => $total,
            'active_percentage' => $total > 0
                ? round(($result['Actif'] / $total) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get transition statistics.
     */
    public function getTransitionStatistics(?string $startDate = null, ?string $endDate = null): array
    {
        $query = StudentStatusHistory::on('tenant')
            ->selectRaw('old_status, new_status, count(*) as count')
            ->groupBy('old_status', 'new_status');

        if ($startDate && $endDate) {
            $query->whereBetween('effective_date', [$startDate, $endDate]);
        }

        return $query->get()->toArray();
    }

    /**
     * Check if a status is final (no transitions allowed).
     */
    public function isFinalStatus(string $status): bool
    {
        return empty(self::ALLOWED_TRANSITIONS[$status] ?? []);
    }
}
