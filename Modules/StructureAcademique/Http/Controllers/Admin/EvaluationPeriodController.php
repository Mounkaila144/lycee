<?php

namespace Modules\StructureAcademique\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\StructureAcademique\Entities\AcademicPeriod;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Http\Requests\StoreEvaluationPeriodRequest;
use Modules\StructureAcademique\Http\Requests\UpdateEvaluationPeriodRequest;
use Modules\StructureAcademique\Http\Resources\AcademicPeriodResource;

class EvaluationPeriodController extends Controller
{
    /**
     * Liste des périodes d'évaluations d'un semestre
     */
    public function index(Request $request, int $semester)
    {
        $semester = Semester::on('tenant')->findOrFail($semester);

        $periods = AcademicPeriod::on('tenant')
            ->where('semester_id', $semester->id)
            ->when($request->type, fn ($q, $type) => $q->where('type', $type)
            )
            ->orderBy('start_date')
            ->get();

        return AcademicPeriodResource::collection($periods);
    }

    /**
     * Créer une période d'évaluation
     */
    public function store(StoreEvaluationPeriodRequest $request, int $semester): JsonResponse
    {
        $semester = Semester::on('tenant')->findOrFail($semester);

        $period = $semester->academicPeriods()->create($request->validated());

        return response()->json([
            'message' => 'Période d\'évaluation créée avec succès.',
            'data' => new AcademicPeriodResource($period),
        ], 201);
    }

    /**
     * Détails d'une période d'évaluation
     */
    public function show(int $semester, int $period)
    {
        $semester = Semester::on('tenant')->findOrFail($semester);
        $period = AcademicPeriod::on('tenant')->findOrFail($period);

        // Vérifier que la période appartient au semestre
        if ($period->semester_id !== $semester->id) {
            return response()->json([
                'message' => 'Cette période n\'appartient pas à ce semestre.',
            ], 404);
        }

        return new AcademicPeriodResource($period->load('semester'));
    }

    /**
     * Modifier une période d'évaluation
     */
    public function update(UpdateEvaluationPeriodRequest $request, int $semester, int $period): JsonResponse
    {
        $semester = Semester::on('tenant')->findOrFail($semester);
        $period = AcademicPeriod::on('tenant')->findOrFail($period);

        // Vérifier que la période appartient au semestre
        if ($period->semester_id !== $semester->id) {
            return response()->json([
                'message' => 'Cette période n\'appartient pas à ce semestre.',
            ], 404);
        }

        $period->update($request->validated());

        return response()->json([
            'message' => 'Période d\'évaluation modifiée avec succès.',
            'data' => new AcademicPeriodResource($period),
        ]);
    }

    /**
     * Supprimer une période d'évaluation
     */
    public function destroy(int $semester, int $period): JsonResponse
    {
        $semester = Semester::on('tenant')->findOrFail($semester);
        $period = AcademicPeriod::on('tenant')->findOrFail($period);

        // Vérifier que la période appartient au semestre
        if ($period->semester_id !== $semester->id) {
            return response()->json([
                'message' => 'Cette période n\'appartient pas à ce semestre.',
            ], 404);
        }

        $period->delete();

        return response()->json([
            'message' => 'Période d\'évaluation supprimée avec succès.',
        ]);
    }

    /**
     * Obtenir les périodes actives
     */
    public function active(Request $request): JsonResponse
    {
        $activePeriods = AcademicPeriod::on('tenant')
            ->active()
            ->with('semester.academicYear')
            ->get();

        return response()->json([
            'data' => AcademicPeriodResource::collection($activePeriods),
        ]);
    }

    /**
     * Obtenir les périodes à venir
     */
    public function upcoming(Request $request): JsonResponse
    {
        $upcomingPeriods = AcademicPeriod::on('tenant')
            ->upcoming()
            ->with('semester.academicYear')
            ->orderBy('start_date')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => AcademicPeriodResource::collection($upcomingPeriods),
        ]);
    }

    /**
     * Calendrier annuel des évaluations (toutes périodes)
     */
    public function calendar(Request $request): JsonResponse
    {
        $query = AcademicPeriod::on('tenant')
            ->with('semester.academicYear');

        // Filtrer par année académique si spécifié
        if ($request->academic_year_id) {
            $query->whereHas('semester', function ($q) use ($request) {
                $q->where('academic_year_id', $request->academic_year_id);
            });
        }

        // Filtrer par type si spécifié
        if ($request->type) {
            $query->where('type', $request->type);
        }

        $periods = $query->orderBy('start_date')->get();

        return response()->json([
            'data' => AcademicPeriodResource::collection($periods),
        ]);
    }
}
