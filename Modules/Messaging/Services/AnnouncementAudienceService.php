<?php

namespace Modules\Messaging\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Enrollment\Entities\ClassEnrollment;
use Modules\Enrollment\Entities\Student;
use Modules\Messaging\Entities\Announcement;
use Modules\NotesEvaluations\Entities\TeacherAssignment;
use Modules\PortailParent\Entities\ParentModel;
use Modules\StructureAcademique\Entities\Classe;
use Modules\UsersGuard\Entities\TenantUser;

/**
 * Règles de diffusion des annonces :
 *  - Administrator / Manager : toutes les annonces ;
 *  - Parent : audiences « all » et « parents », classe vide ou classe d'un de ses enfants ;
 *  - Élève : audiences « all » et « students », classe vide ou sa classe ;
 *  - Professeur : audiences « all » et « teachers », classe vide ou une de ses classes (PP / affectations) ;
 *  - Caissier / Comptable : audiences « all » et « staff », annonces sans classe uniquement.
 */
class AnnouncementAudienceService
{
    public const MANAGER_ROLES = ['Administrator', 'Manager'];

    public function tableExists(): bool
    {
        return Schema::connection('tenant')->hasTable('announcements');
    }

    /**
     * Annonces publiées, non expirées et visibles par l'utilisateur (épinglées d'abord).
     *
     * @return Collection<int, Announcement>
     */
    public function feedFor(TenantUser $user, int $limit = 20): Collection
    {
        if (! $this->tableExists()) {
            return collect();
        }

        $query = Announcement::query()
            ->with(['classe:id,name', 'author:id,username,firstname,lastname'])
            ->currentlyVisible();

        if (! $user->hasAnyRole(self::MANAGER_ROLES)) {
            $scopes = $this->audienceScopesFor($user);

            if ($scopes === []) {
                return collect();
            }

            $query->where(function (Builder $visibility) use ($scopes): void {
                foreach ($scopes as $scope) {
                    $visibility->orWhere(function (Builder $clause) use ($scope): void {
                        $clause->whereIn('audience', [Announcement::AUDIENCE_ALL, $scope['audience']])
                            ->where(fn (Builder $classe) => $classe->whereNull('classe_id')
                                ->orWhereIn('classe_id', $scope['classe_ids']));
                    });
                }
            });
        }

        return $query
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Une entrée par rôle « destinataire » de l'utilisateur.
     *
     * @return array<int, array{audience: string, classe_ids: array<int, int>}>
     */
    public function audienceScopesFor(TenantUser $user): array
    {
        $scopes = [];

        if ($user->hasRole('Parent')) {
            $childIds = ParentModel::query()
                ->where('user_id', $user->id)
                ->with('students:id')
                ->get()
                ->flatMap(fn (ParentModel $parent) => $parent->students->pluck('id'))
                ->unique()
                ->values()
                ->all();

            $scopes[] = ['audience' => Announcement::AUDIENCE_PARENTS, 'classe_ids' => $this->activeClasseIdsOfStudents($childIds)];
        }

        if ($user->hasRole('Élève')) {
            $studentIds = Student::query()->where('user_id', $user->id)->pluck('id')->all();

            $scopes[] = ['audience' => Announcement::AUDIENCE_STUDENTS, 'classe_ids' => $this->activeClasseIdsOfStudents($studentIds)];
        }

        if ($user->hasRole('Professeur')) {
            $scopes[] = ['audience' => Announcement::AUDIENCE_TEACHERS, 'classe_ids' => $this->classeIdsOfTeacher((int) $user->id)];
        }

        if ($user->hasAnyRole(['Caissier', 'Comptable'])) {
            $scopes[] = ['audience' => Announcement::AUDIENCE_STAFF, 'classe_ids' => []];
        }

        return $scopes;
    }

    /**
     * Libellé lisible et estimation du nombre de comptes ciblés.
     *
     * @return array{label: string, counts: array{parents: int, students: int, teachers: int, staff: int}, total: int}
     */
    public function preview(string $audience, ?int $classeId): array
    {
        $classe = $classeId !== null ? Classe::query()->find($classeId) : null;
        $wants = fn (string $target): bool => $audience === Announcement::AUDIENCE_ALL || $audience === $target;

        $studentIds = $classe !== null
            ? ClassEnrollment::query()
                ->where('classe_id', $classe->id)
                ->where('status', 'Actif')
                ->pluck('student_id')
                ->all()
            : null;

        $counts = [
            'parents' => $wants(Announcement::AUDIENCE_PARENTS) ? $this->countParents($studentIds) : 0,
            'students' => $wants(Announcement::AUDIENCE_STUDENTS) ? $this->countStudents($studentIds) : 0,
            'teachers' => $wants(Announcement::AUDIENCE_TEACHERS) ? $this->countTeachers($classe) : 0,
            'staff' => $wants(Announcement::AUDIENCE_STAFF) && $classe === null ? $this->countUsersWithRoles(['Caissier', 'Comptable']) : 0,
        ];

        $label = Announcement::AUDIENCE_LABELS[$audience] ?? $audience;

        if ($classe !== null) {
            $label .= ' — classe '.$classe->name;
        }

        return ['label' => $label, 'counts' => $counts, 'total' => array_sum($counts)];
    }

    /**
     * @param  array<int, int>  $studentIds
     * @return array<int, int>
     */
    private function activeClasseIdsOfStudents(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        return ClassEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', 'Actif')
            ->whereHas('academicYear', fn ($query) => $query->where('is_active', true))
            ->pluck('classe_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function classeIdsOfTeacher(int $userId): array
    {
        $classeIds = Classe::query()->where('head_teacher_id', $userId)->pluck('id');

        if (Schema::connection('tenant')->hasTable('teacher_assignments')) {
            $classeIds = $classeIds->merge(TeacherAssignment::query()->where('teacher_id', $userId)->pluck('classe_id'));
        }

        return $classeIds->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }

    /**
     * @param  array<int, int>|null  $studentIds  null = tous les parents
     */
    private function countParents(?array $studentIds): int
    {
        if ($studentIds === null) {
            return $this->countUsersWithRoles(['Parent']);
        }

        return ParentModel::query()
            ->whereNotNull('user_id')
            ->whereHas('students', fn ($query) => $query->whereIn('students.id', $studentIds))
            ->distinct()
            ->count('user_id');
    }

    /**
     * @param  array<int, int>|null  $studentIds  null = tous les élèves
     */
    private function countStudents(?array $studentIds): int
    {
        if ($studentIds === null) {
            return $this->countUsersWithRoles(['Élève']);
        }

        return Student::query()
            ->whereIn('id', $studentIds)
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');
    }

    private function countTeachers(?Classe $classe): int
    {
        if ($classe === null) {
            return $this->countUsersWithRoles(['Professeur']);
        }

        $teacherIds = collect([$classe->head_teacher_id])->filter();

        if (Schema::connection('tenant')->hasTable('teacher_assignments')) {
            $teacherIds = $teacherIds->merge(TeacherAssignment::query()->where('classe_id', $classe->id)->pluck('teacher_id'));
        }

        return $teacherIds->unique()->count();
    }

    /**
     * @param  array<int, string>  $roles
     */
    private function countUsersWithRoles(array $roles): int
    {
        return TenantUser::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roles))
            ->count();
    }
}
