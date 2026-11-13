<?php

namespace Modules\StructureAcademique\Http\Controllers\Teacher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Enrollment\Http\Resources\StudentResource;
use Modules\NotesEvaluations\Entities\TeacherAssignment;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Http\Resources\TeacherClasseResource;

/**
 * Espace Professeur — « Mes classes ».
 *
 * Un professeur voit les classes de l'année scolaire active dont il est
 * professeur principal (classes.head_teacher_id) ou dans lesquelles il a une
 * affectation de matière (teacher_assignments), ainsi que leurs élèves.
 * L'Administrator garde l'accès aux élèves de toutes les classes (debug).
 */
class MyClassesController extends Controller
{
    /**
     * Classes de l'année active dont l'utilisateur connecté est professeur principal
     * ou dans lesquelles il enseigne une matière (avec la liste de ses matières).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = (int) $request->user()->id;
        $assignments = $this->assignmentsOf($userId);

        $classes = Classe::on('tenant')
            ->with(['level', 'series', 'academicYear'])
            ->withCount(['activeEnrollments as students_count'])
            ->where(function ($query) use ($userId, $assignments) {
                $query->where('head_teacher_id', $userId)
                    ->orWhereIn('id', $assignments->pluck('classe_id')->unique()->values());
            })
            ->whereHas('academicYear', fn ($query) => $query->where('is_active', true))
            ->orderByRaw('(SELECT order_index FROM levels WHERE levels.id = classes.level_id)')
            ->orderBy('name')
            ->get()
            ->each(function (Classe $classe) use ($userId, $assignments): void {
                $classe->setAttribute('is_head_teacher', (int) $classe->head_teacher_id === $userId);
                $classe->setAttribute('teacher_subjects', $assignments
                    ->filter(fn (TeacherAssignment $assignment): bool => (int) $assignment->classe_id === (int) $classe->id
                        && (int) $assignment->academic_year_id === (int) $classe->academic_year_id)
                    ->map(fn (TeacherAssignment $assignment): array => [
                        'id' => $assignment->subject?->id,
                        'name' => $assignment->subject?->name,
                        'code' => $assignment->subject?->code,
                    ])
                    ->values()
                    ->all());
            });

        return TeacherClasseResource::collection($classes);
    }

    /**
     * Élèves actifs d'une classe dont l'utilisateur connecté est professeur principal.
     */
    public function students(Request $request, int $classe): AnonymousResourceCollection
    {
        $classe = Classe::on('tenant')->findOrFail($classe);
        $user = $request->user();

        $isAssigned = $this->assignmentsOf((int) $user->id)->contains(
            fn (TeacherAssignment $assignment): bool => (int) $assignment->classe_id === (int) $classe->id
        );

        if ((int) $classe->head_teacher_id !== (int) $user->id && ! $isAssigned && ! $user->hasRole('Administrator')) {
            abort(403, "Vous n'êtes ni professeur principal ni enseignant de cette classe.");
        }

        $perPage = min(max($request->integer('per_page', 100), 1), 200);

        $students = $classe->students()
            ->wherePivot('academic_year_id', $classe->academic_year_id)
            ->wherePivot('status', 'Actif')
            ->wherePivotNull('deleted_at')
            ->when($request->search, function ($query, string $search) {
                $query->where(function ($studentQuery) use ($search) {
                    $studentQuery
                        ->where('matricule', 'like', "%{$search}%")
                        ->orWhere('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%");
                });
            })
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->paginate($perPage);

        return StudentResource::collection($students);
    }

    /**
     * Affectations (classe, matière) du professeur — vide tant que la migration
     * teacher_assignments n'est pas jouée sur le tenant.
     *
     * @return Collection<int, TeacherAssignment>
     */
    private function assignmentsOf(int $userId): Collection
    {
        if (! Schema::connection('tenant')->hasTable('teacher_assignments')) {
            return collect();
        }

        return TeacherAssignment::query()
            ->with('subject:id,name,code')
            ->where('teacher_id', $userId)
            ->get();
    }
}
