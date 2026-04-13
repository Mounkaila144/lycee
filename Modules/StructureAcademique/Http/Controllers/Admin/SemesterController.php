<?php

namespace Modules\StructureAcademique\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Http\Requests\UpdateSemesterRequest;
use Modules\StructureAcademique\Http\Resources\SemesterResource;

class SemesterController extends Controller
{
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
     * Obtenir le semestre courant (basé sur la date du jour)
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
