<?php

namespace Modules\StructureAcademique\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\StructureAcademique\Entities\AcademicPeriod;
use Modules\StructureAcademique\Entities\Semester;
use Modules\StructureAcademique\Http\Requests\StoreAcademicPeriodRequest;
use Modules\StructureAcademique\Http\Requests\UpdateAcademicPeriodRequest;
use Modules\StructureAcademique\Http\Resources\AcademicPeriodResource;

class AcademicPeriodController extends Controller
{
    /**
     * Liste des périodes académiques avec filtres
     */
    public function index(Request $request): JsonResponse
    {
        $query = AcademicPeriod::on('tenant');

        // Filtrer par semestre
        if ($request->has('semester_id')) {
            $query->where('semester_id', $request->semester_id);
        }

        // Filtrer par type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filtrer par statut (active, upcoming, past)
        if ($request->status === 'active') {
            $query->active();
        } elseif ($request->status === 'upcoming') {
            $query->upcoming();
        }

        $periods = $query->with('semester')
            ->orderBy('start_date')
            ->get();

        return response()->json([
            'data' => AcademicPeriodResource::collection($periods),
        ]);
    }

    /**
     * Créer une nouvelle période académique
     */
    public function store(StoreAcademicPeriodRequest $request): JsonResponse
    {
        $period = AcademicPeriod::on('tenant')->create($request->validated());

        return response()->json([
            'message' => 'Période académique créée avec succès.',
            'data' => new AcademicPeriodResource($period->load('semester')),
        ], 201);
    }

    /**
     * Afficher les détails d'une période
     */
    public function show(int $period): JsonResponse
    {
        $period = AcademicPeriod::on('tenant')->with('semester')->findOrFail($period);

        return response()->json([
            'data' => new AcademicPeriodResource($period),
        ]);
    }

    /**
     * Modifier une période académique
     */
    public function update(UpdateAcademicPeriodRequest $request, int $period): JsonResponse
    {
        $period = AcademicPeriod::on('tenant')->findOrFail($period);
        $period->update($request->validated());

        return response()->json([
            'message' => 'Période académique modifiée avec succès.',
            'data' => new AcademicPeriodResource($period->load('semester')),
        ]);
    }

    /**
     * Supprimer une période académique
     */
    public function destroy(int $period): JsonResponse
    {
        $period = AcademicPeriod::on('tenant')->findOrFail($period);
        $period->delete();

        return response()->json([
            'message' => 'Période académique supprimée avec succès.',
        ]);
    }

    /**
     * Obtenir les périodes actives
     */
    public function active(): JsonResponse
    {
        $periods = AcademicPeriod::on('tenant')
            ->active()
            ->with('semester')
            ->orderBy('start_date')
            ->get();

        return response()->json([
            'data' => AcademicPeriodResource::collection($periods),
        ]);
    }

    /**
     * Obtenir les périodes à venir
     */
    public function upcoming(): JsonResponse
    {
        $periods = AcademicPeriod::on('tenant')
            ->upcoming()
            ->with('semester')
            ->orderBy('start_date')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => AcademicPeriodResource::collection($periods),
        ]);
    }

    /**
     * Obtenir le calendrier complet
     */
    public function calendar(Request $request): JsonResponse
    {
        $query = AcademicPeriod::on('tenant')->with('semester');

        // Filtrer par année académique si fournie
        if ($request->has('academic_year_id')) {
            $query->whereHas('semester', function ($q) use ($request) {
                $q->where('academic_year_id', $request->academic_year_id);
            });
        }

        $periods = $query->orderBy('start_date')->get();

        return response()->json([
            'data' => AcademicPeriodResource::collection($periods),
        ]);
    }
}
