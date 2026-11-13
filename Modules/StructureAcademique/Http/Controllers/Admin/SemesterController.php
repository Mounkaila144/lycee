<?php

namespace Modules\StructureAcademique\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Http\Requests\StoreSemesterRequest;
use Modules\StructureAcademique\Http\Requests\UpdateSemesterRequest;
use Modules\StructureAcademique\Http\Resources\SemesterResource;

class SemesterController extends Controller
{
    /**
     * Liste des semestres (filtrable par academic_year_id)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Semester::on('tenant')->with('academicYear');

        if ($request->has('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        $semesters = $query->orderBy('start_date')->get();

        return response()->json([
            'data' => SemesterResource::collection($semesters),
        ]);
    }

    /**
     * Créer un semestre
     */
    public function store(StoreSemesterRequest $request): JsonResponse
    {
        $semester = Semester::on('tenant')->create($request->validated());

        return response()->json([
            'message' => 'Semestre créé avec succès.',
            'data' => new SemesterResource($semester->load('academicYear')),
        ], 201);
    }

    /**
     * Détails d'un semestre
     */
    public function show(int $semester): JsonResponse
    {
        $semester = Semester::on('tenant')->with('academicYear')->findOrFail($semester);

        return response()->json([
            'data' => new SemesterResource($semester),
        ]);
    }

    /**
     * Modifier les dates d'un semestre
     */
    public function update(UpdateSemesterRequest $request, int $semester): JsonResponse
    {
        $semester = Semester::on('tenant')->findOrFail($semester);
        $semester->update($request->validated());

        return response()->json([
            'message' => 'Semestre modifié avec succès.',
            'data' => new SemesterResource($semester->load('academicYear')),
        ]);
    }

    /**
     * Supprimer un semestre
     */
    public function destroy(int $semester): JsonResponse
    {
        $semester = Semester::on('tenant')->findOrFail($semester);
        $semester->delete();

        return response()->json([
            'message' => 'Semestre supprimé avec succès.',
        ]);
    }

    /**
     * Clôturer un semestre
     */
    public function close(int $semester): JsonResponse
    {
        $semester = Semester::on('tenant')->findOrFail($semester);

        if ($semester->closed_at !== null) {
            return response()->json([
                'message' => 'Ce semestre est déjà clôturé.',
            ], 422);
        }

        $semester->update([
            'closed_at' => now(),
            'is_active' => false,
        ]);

        return response()->json([
            'message' => 'Semestre clôturé avec succès.',
            'data' => new SemesterResource($semester->load('academicYear')),
        ]);
    }

    /**
     * Obtenir le semestre courant
     */
    public function current(): JsonResponse
    {
        $semester = Semester::on('tenant')
            ->current()
            ->with(['academicYear'])
            ->first();

        if (! $semester) {
            return response()->json([
                'message' => 'Aucun semestre en cours.',
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => new SemesterResource($semester),
        ]);
    }
}
