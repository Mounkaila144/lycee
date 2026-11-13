<?php

namespace Modules\NotesEvaluations\Services\Lycee;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\ClassEvaluation;
use Modules\NotesEvaluations\Entities\TeacherAssignment;
use Modules\StructureAcademique\Entities\Classe;

/**
 * Droits des notes lycée :
 *  - Administrator / Manager : tout ;
 *  - Professeur : uniquement les (classe, matière) de ses affectations pour l'année de la classe ;
 *    le professeur principal (classes.head_teacher_id) voit toute sa classe ;
 *  - Élève : lui-même ; Parent : ses enfants (notes publiées uniquement).
 */
class LyceeGradeAccessService
{
    /**
     * @var list<string>
     */
    public const SUPERVISOR_ROLES = ['Administrator', 'Manager'];

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

    public function isTeacher(?Authenticatable $user): bool
    {
        return $this->hasAnyRole($user, ['Professeur']);
    }

    public function isHeadTeacher(Authenticatable $user, Classe $classe): bool
    {
        return $classe->head_teacher_id !== null
            && (int) $classe->head_teacher_id === (int) $user->getAuthIdentifier();
    }

    /**
     * Affectations de l'enseignant (toutes années, ou une année donnée).
     *
     * @return Collection<int, TeacherAssignment>
     */
    public function assignmentsOf(Authenticatable $user, ?int $academicYearId = null): Collection
    {
        return TeacherAssignment::query()
            ->where('teacher_id', $user->getAuthIdentifier())
            ->when($academicYearId !== null, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->get();
    }

    /**
     * Matières affectées à l'enseignant dans la classe (année de la classe).
     *
     * @return list<int>
     */
    public function assignedSubjectIds(Authenticatable $user, Classe $classe): array
    {
        return TeacherAssignment::query()
            ->where('teacher_id', $user->getAuthIdentifier())
            ->where('classe_id', $classe->id)
            ->where('academic_year_id', $classe->academic_year_id)
            ->pluck('subject_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Peut gérer les évaluations/notes d'une matière d'une classe.
     */
    public function canTeach(Authenticatable $user, Classe $classe, int $subjectId): bool
    {
        if ($this->isSupervisor($user)) {
            return true;
        }

        return $this->isTeacher($user)
            && in_array($subjectId, $this->assignedSubjectIds($user, $classe), true);
    }

    public function canManageEvaluation(Authenticatable $user, ClassEvaluation $evaluation): bool
    {
        $classe = $evaluation->classe;

        return $classe !== null && $this->canTeach($user, $classe, (int) $evaluation->subject_id);
    }

    /**
     * Peut consulter les résultats de la classe (au moins partiellement).
     */
    public function canViewClassResults(Authenticatable $user, Classe $classe): bool
    {
        if ($this->isSupervisor($user)) {
            return true;
        }

        return $this->isTeacher($user)
            && ($this->isHeadTeacher($user, $classe) || $this->assignedSubjectIds($user, $classe) !== []);
    }

    /**
     * Matières visibles dans les résultats d'une classe. null = toutes (Admin/Manager, PP).
     *
     * @return list<int>|null
     */
    public function visibleSubjectIds(Authenticatable $user, Classe $classe): ?array
    {
        if ($this->isSupervisor($user) || $this->isHeadTeacher($user, $classe)) {
            return null;
        }

        return $this->assignedSubjectIds($user, $classe);
    }

    /**
     * Peut éditer le bulletin complet de la classe (Admin/Manager, PP).
     */
    public function canViewFullClass(Authenticatable $user, Classe $classe): bool
    {
        return $this->isSupervisor($user)
            || ($this->isTeacher($user) && $this->isHeadTeacher($user, $classe));
    }

    /**
     * L'utilisateur est-il l'élève lui-même ou un parent de l'élève ?
     */
    public function isFamilyOf(Authenticatable $user, Student $student): bool
    {
        $userId = (int) $user->getAuthIdentifier();

        if ($this->hasAnyRole($user, ['Élève']) && $student->user_id !== null && (int) $student->user_id === $userId) {
            return true;
        }

        return $this->hasAnyRole($user, ['Parent'])
            && $student->parents()->where('parents.user_id', $userId)->exists();
    }

    /**
     * Peut consulter le relevé de notes d'un élève dans une classe donnée (classe null = pas de classe).
     */
    public function canViewStudentReport(Authenticatable $user, Student $student, ?Classe $classe): bool
    {
        if ($this->isSupervisor($user) || $this->isFamilyOf($user, $student)) {
            return true;
        }

        return $classe !== null && $this->canViewClassResults($user, $classe);
    }

    /**
     * Peut saisir une appréciation (matière : prof affecté ; générale : PP ; Admin/Manager : tout).
     */
    public function canComment(Authenticatable $user, Classe $classe, ?int $subjectId): bool
    {
        if ($this->isSupervisor($user)) {
            return true;
        }

        if (! $this->isTeacher($user)) {
            return false;
        }

        if ($subjectId === null) {
            return $this->isHeadTeacher($user, $classe);
        }

        return in_array($subjectId, $this->assignedSubjectIds($user, $classe), true);
    }
}
