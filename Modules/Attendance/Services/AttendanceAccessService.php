<?php

namespace Modules\Attendance\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Schema;
use Modules\Attendance\Entities\AbsenceJustification;
use Modules\Attendance\Entities\AttendanceSession;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\TeacherAssignment;
use Modules\StructureAcademique\Entities\Classe;
use Modules\Timetable\Entities\TimetableSlot;

/**
 * Règles d'accès du module Présences :
 *  - Administrator / Manager : accès complet ;
 *  - Professeur : uniquement ses classes (professeur principal, créneau d'emploi du temps
 *    ou affectation de matière — teacher_assignments) ;
 *  - Élève : uniquement lui-même ; Parent : uniquement ses enfants.
 */
class AttendanceAccessService
{
    /**
     * @var list<string>
     */
    public const SUPERVISOR_ROLES = ['Administrator', 'Manager'];

    private ?bool $timetableSlotsHaveClasse = null;

    private ?bool $sessionsHaveClasse = null;

    private ?bool $justificationsTableExists = null;

    private ?bool $teacherAssignmentsTableExists = null;

    public function hasAnyRole(?Authenticatable $user, array $roles): bool
    {
        return $user !== null
            && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole($roles);
    }

    public function isSupervisor(?Authenticatable $user): bool
    {
        return $this->hasAnyRole($user, self::SUPERVISOR_ROLES);
    }

    public function canManageClasse(Authenticatable $user, Classe $classe): bool
    {
        return $this->isSupervisor($user)
            || in_array((int) $classe->id, $this->teacherClasseIds($user), true);
    }

    /**
     * Classes d'un enseignant : celles dont il est professeur principal, celles
     * où il a au moins un créneau d'emploi du temps et celles où une matière lui est affectée.
     *
     * @return list<int>
     */
    public function teacherClasseIds(Authenticatable $user): array
    {
        $teacherId = (int) $user->getAuthIdentifier();

        $classeIds = Classe::query()
            ->where('head_teacher_id', $teacherId)
            ->pluck('id');

        if ($this->timetableSlotsHaveClasse()) {
            $classeIds = $classeIds->merge(
                TimetableSlot::query()
                    ->where('teacher_id', $teacherId)
                    ->whereNotNull('classe_id')
                    ->pluck('classe_id')
            );
        }

        if ($this->teacherAssignmentsTableExists()) {
            $classeIds = $classeIds->merge(
                TeacherAssignment::query()
                    ->where('teacher_id', $teacherId)
                    ->pluck('classe_id')
            );
        }

        return $classeIds->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Tolérance tant que la migration 2026_11_14_000001 (notes lycée) n'est pas jouée sur le tenant.
     */
    public function teacherAssignmentsTableExists(): bool
    {
        return $this->teacherAssignmentsTableExists ??= Schema::connection('tenant')->hasTable('teacher_assignments');
    }

    /**
     * Tolérance tant que la migration 2026_11_13_100000 n'est pas jouée sur le tenant.
     */
    public function sessionsHaveClasse(): bool
    {
        return $this->sessionsHaveClasse ??= Schema::connection('tenant')->hasColumn('attendance_sessions', 'classe_id');
    }

    /**
     * Tolérance tant que la migration 2026_11_13_100001 n'est pas jouée sur le tenant.
     */
    public function justificationsTableExists(): bool
    {
        return $this->justificationsTableExists ??= Schema::connection('tenant')->hasTable('attendance_justifications');
    }

    public function timetableSlotsHaveClasse(): bool
    {
        return $this->timetableSlotsHaveClasse ??= Schema::connection('tenant')->hasColumn('timetable_slots', 'classe_id');
    }

    public function canManageTimetableSlot(Authenticatable $user, TimetableSlot $slot): bool
    {
        return $this->isSupervisor($user)
            || (int) $slot->teacher_id === (int) $user->getAuthIdentifier();
    }

    public function canManageSession(Authenticatable $user, AttendanceSession $session): bool
    {
        if ($this->isSupervisor($user)) {
            return true;
        }

        if ((int) $session->created_by === (int) $user->getAuthIdentifier()) {
            return true;
        }

        if ($session->classe && $this->canManageClasse($user, $session->classe)) {
            return true;
        }

        return $session->timetableSlot !== null
            && $this->canManageTimetableSlot($user, $session->timetableSlot);
    }

    /**
     * Fiches élèves (students.id) accessibles par un Parent (ses enfants) ou un Élève (lui-même).
     *
     * @return list<int>
     */
    public function ownStudentIds(Authenticatable $user): array
    {
        $userId = (int) $user->getAuthIdentifier();

        if ($this->hasAnyRole($user, ['Parent'])) {
            return Student::query()
                ->whereHas('parents', fn ($query) => $query->where('parents.user_id', $userId))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($this->hasAnyRole($user, ['Élève'])) {
            return Student::query()
                ->where('user_id', $userId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return [];
    }

    /**
     * Comptes utilisateurs (users.id) des élèves accessibles par un Parent / Élève.
     *
     * @return list<int>
     */
    public function ownStudentUserIds(Authenticatable $user): array
    {
        return Student::query()
            ->whereIn('id', $this->ownStudentIds($user))
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Comptes utilisateurs (users.id) des élèves inscrits (actifs) dans les classes
     * de l'enseignant (professeur principal ou créneau d'emploi du temps).
     *
     * @return list<int>
     */
    public function teacherStudentUserIds(Authenticatable $user): array
    {
        return Student::query()
            ->whereNotNull('user_id')
            ->whereHas('classEnrollments', function ($query) use ($user) {
                $query->where('status', 'Actif')
                    ->whereIn('classe_id', $this->teacherClasseIds($user));
            })
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Restreint (si nécessaire) une liste d'élèves (users.id) visibles par l'utilisateur.
     * Retourne null lorsque l'utilisateur voit tout (Administrator / Manager).
     *
     * @return list<int>|null
     */
    public function visibleStudentUserIds(Authenticatable $user): ?array
    {
        if ($this->isSupervisor($user)) {
            return null;
        }

        if ($this->hasAnyRole($user, ['Professeur'])) {
            return $this->teacherStudentUserIds($user);
        }

        return $this->ownStudentUserIds($user);
    }

    public function canViewStudent(Authenticatable $user, int $studentUserId): bool
    {
        $visible = $this->visibleStudentUserIds($user);

        return $visible === null || in_array($studentUserId, $visible, true);
    }

    public function canViewJustification(Authenticatable $user, AbsenceJustification $justification): bool
    {
        return $this->canViewStudent($user, (int) $justification->student_id)
            || (int) $justification->submitted_by === (int) $user->getAuthIdentifier();
    }
}
