<?php

namespace Modules\StructureAcademique\Http\Controllers\Teacher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Enrollment\Http\Resources\StudentResource;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Http\Resources\ClasseResource;

/**
 * Espace Professeur — « Mes classes ».
 *
 * Un professeur voit les classes de l'année scolaire active dont il est
 * professeur principal (classes.head_teacher_id), ainsi que leurs élèves.
 * L'Administrator garde l'accès aux élèves de toutes les classes (debug).
 */
class MyClassesController extends Controller
{
    /**
     * Classes de l'année active dont l'utilisateur connecté est professeur principal.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $classes = Classe::on('tenant')
            ->with(['level', 'series', 'academicYear'])
            ->withCount(['activeEnrollments as students_count'])
            ->where('head_teacher_id', $request->user()->id)
            ->whereHas('academicYear', fn ($query) => $query->where('is_active', true))
            ->orderByRaw('(SELECT order_index FROM levels WHERE levels.id = classes.level_id)')
            ->orderBy('name')
            ->get();

        return ClasseResource::collection($classes);
    }

    /**
     * Élèves actifs d'une classe dont l'utilisateur connecté est professeur principal.
     */
    public function students(Request $request, int $classe): AnonymousResourceCollection
    {
        $classe = Classe::on('tenant')->findOrFail($classe);
        $user = $request->user();

        if ((int) $classe->head_teacher_id !== (int) $user->id && ! $user->hasRole('Administrator')) {
            abort(403, "Vous n'êtes pas professeur principal de cette classe.");
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
}
