<?php

namespace Modules\StructureAcademique\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Http\Requests\StoreClasseRequest;
use Modules\StructureAcademique\Http\Requests\UpdateClasseRequest;
use Modules\StructureAcademique\Http\Resources\ClasseResource;
use Modules\UsersGuard\Entities\User;

class ClasseController extends Controller
{
    /**
     * Liste paginée des classes avec filtres
     */
    public function index(Request $request)
    {
        $classes = Classe::on('tenant')
            ->with(['level.cycle', 'series', 'headTeacher', 'academicYear'])
            ->when($request->cycle_id, fn ($q, $cycleId) => $q->whereHas('level', fn ($q2) => $q2->where('cycle_id', $cycleId)))
            ->when($request->level_id, fn ($q, $levelId) => $q->where('level_id', $levelId))
            ->when($request->series_id, fn ($q, $seriesId) => $q->where('series_id', $seriesId))
            ->when(
                $request->academic_year_id,
                fn ($q, $yearId) => $q->where('academic_year_id', $yearId),
                fn ($q) => $q->whereHas('academicYear', fn ($q2) => $q2->where('is_active', true))
            )
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderByRaw('(SELECT order_index FROM levels WHERE levels.id = classes.level_id)')
            ->orderByRaw("COALESCE((SELECT code FROM series WHERE series.id = classes.series_id), '')")
            ->orderBy('section')
            ->paginate($request->per_page ?? 15);

        return ClasseResource::collection($classes);
    }

    /**
     * Créer une classe avec auto-génération du nom
     */
    public function store(StoreClasseRequest $request): JsonResponse
    {
        $data = $request->validated();

        $classe = new Classe($data);
        $classe->setConnection('tenant');
        $classe->name = $classe->generateName();

        // Vérifier unicité du nom pour cette année
        $exists = Classe::on('tenant')
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('name', $classe->name)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Une classe avec ce nom existe déjà pour cette année scolaire.',
                'errors' => ['name' => ['Une classe avec ce nom existe déjà pour cette année scolaire.']],
            ], 422);
        }

        $classe->save();

        return response()->json([
            'message' => 'Classe créée avec succès.',
            'data' => new ClasseResource($classe->load(['level.cycle', 'series', 'headTeacher', 'academicYear'])),
        ], 201);
    }

    /**
     * Détails d'une classe
     */
    public function show(int $classe): ClasseResource
    {
        $classe = Classe::on('tenant')
            ->with(['level.cycle', 'series', 'headTeacher', 'academicYear'])
            ->findOrFail($classe);

        return new ClasseResource($classe);
    }

    /**
     * Modifier une classe
     */
    public function update(UpdateClasseRequest $request, int $classe): JsonResponse
    {
        $classe = Classe::on('tenant')->findOrFail($classe);

        try {
            $classe->update($request->validated());
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1062) {
                return response()->json([
                    'message' => 'Une classe avec ce nom existe déjà pour cette année scolaire.',
                    'errors' => ['name' => ['Doublon détecté.']],
                ], 422);
            }
            throw $e;
        }

        return response()->json([
            'message' => 'Classe modifiée avec succès.',
            'data' => new ClasseResource($classe->load(['level.cycle', 'series', 'headTeacher', 'academicYear'])),
        ]);
    }

    /**
     * Statistiques globales des classes
     */
    public function stats(Request $request): JsonResponse
    {
        $yearId = $request->academic_year_id;

        if (! $yearId) {
            $activeYear = AcademicYear::on('tenant')->active()->first();
            $yearId = $activeYear?->id;
        }

        $classesQuery = Classe::on('tenant')
            ->where('academic_year_id', $yearId)
            ->whereNull('deleted_at');

        $totalClasses = $classesQuery->count();

        // Count by cycle
        $classesByCycle = \DB::connection('tenant')
            ->table('classes')
            ->join('levels', 'classes.level_id', '=', 'levels.id')
            ->join('cycles', 'levels.cycle_id', '=', 'cycles.id')
            ->where('classes.academic_year_id', $yearId)
            ->whereNull('classes.deleted_at')
            ->select('cycles.id', 'cycles.code', 'cycles.name', \DB::raw('COUNT(*) as count'))
            ->groupBy('cycles.id', 'cycles.code', 'cycles.name')
            ->get();

        // Count by level
        $classesByLevel = \DB::connection('tenant')
            ->table('classes')
            ->join('levels', 'classes.level_id', '=', 'levels.id')
            ->where('classes.academic_year_id', $yearId)
            ->whereNull('classes.deleted_at')
            ->select('levels.id', 'levels.code', 'levels.name', \DB::raw('COUNT(*) as count'))
            ->groupBy('levels.id', 'levels.code', 'levels.name')
            ->orderBy('levels.order_index')
            ->get();

        return response()->json([
            'data' => [
                'total_classes' => $totalClasses,
                'classes_by_cycle' => $classesByCycle,
                'classes_by_level' => $classesByLevel,
            ],
        ]);
    }

    /**
     * Liste des enseignants disponibles comme PP pour une année scolaire
     */
    public function availableHeadTeachers(Request $request): JsonResponse
    {
        $yearId = $request->academic_year_id;

        if (! $yearId) {
            $activeYear = AcademicYear::on('tenant')->active()->first();
            $yearId = $activeYear?->id;
        }

        $usedTeacherIds = Classe::on('tenant')
            ->where('academic_year_id', $yearId)
            ->whereNotNull('head_teacher_id')
            ->whereNull('deleted_at')
            ->pluck('head_teacher_id');

        $excludeClassId = $request->exclude_class_id;

        if ($excludeClassId) {
            $currentPp = Classe::on('tenant')->find($excludeClassId)?->head_teacher_id;
            $usedTeacherIds = $usedTeacherIds->reject(fn ($id) => $id === $currentPp);
        }

        $teachers = User::on('tenant')
            ->whereNotIn('id', $usedTeacherIds)
            ->where('is_active', true)
            ->get(['id', 'firstname', 'lastname', 'email']);

        return response()->json([
            'data' => $teachers->map(fn ($t) => [
                'id' => $t->id,
                'firstname' => $t->firstname,
                'lastname' => $t->lastname,
                'name' => $t->name,
                'email' => $t->email,
            ]),
        ]);
    }

    /**
     * Liste des classes sans professeur principal
     */
    public function withoutHeadTeacher(Request $request): JsonResponse
    {
        $yearId = $request->academic_year_id;

        if (! $yearId) {
            $activeYear = AcademicYear::on('tenant')->active()->first();
            $yearId = $activeYear?->id;
        }

        $classes = Classe::on('tenant')
            ->with(['level.cycle', 'series'])
            ->where('academic_year_id', $yearId)
            ->whereNull('head_teacher_id')
            ->orderByRaw('(SELECT order_index FROM levels WHERE levels.id = classes.level_id)')
            ->get();

        return response()->json([
            'data' => ClasseResource::collection($classes),
            'count' => $classes->count(),
        ]);
    }

    /**
     * Supprimer une classe (seulement si aucun élève inscrit)
     */
    public function destroy(int $classe): JsonResponse
    {
        $classe = Classe::on('tenant')->findOrFail($classe);

        // Vérifier si des élèves sont inscrits (si table existe)
        if (\Schema::connection('tenant')->hasTable('class_enrollments')) {
            $hasStudents = \DB::connection('tenant')
                ->table('class_enrollments')
                ->where('classe_id', $classe->id)
                ->exists();

            if ($hasStudents) {
                return response()->json([
                    'message' => 'Impossible de supprimer cette classe : des élèves y sont inscrits.',
                ], 422);
            }
        }

        $classe->delete();

        return response()->json([
            'message' => 'Classe supprimée avec succès.',
        ]);
    }
}
