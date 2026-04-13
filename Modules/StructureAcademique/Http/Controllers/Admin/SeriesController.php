<?php

namespace Modules\StructureAcademique\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\StructureAcademique\Entities\Series;
use Modules\StructureAcademique\Http\Requests\StoreSeriesRequest;
use Modules\StructureAcademique\Http\Requests\UpdateSeriesRequest;
use Modules\StructureAcademique\Http\Resources\SeriesResource;

class SeriesController extends Controller
{
    /**
     * Liste des séries
     */
    public function index(Request $request)
    {
        $series = Series::on('tenant')
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('code')
            ->get();

        return SeriesResource::collection($series);
    }

    /**
     * Créer une nouvelle série
     */
    public function store(StoreSeriesRequest $request): JsonResponse
    {
        $series = Series::on('tenant')->create($request->validated());

        return response()->json([
            'message' => 'Série créée avec succès.',
            'data' => new SeriesResource($series),
        ], 201);
    }

    /**
     * Détails d'une série
     */
    public function show(int $series): SeriesResource
    {
        $series = Series::on('tenant')->findOrFail($series);

        return new SeriesResource($series);
    }

    /**
     * Modifier une série
     */
    public function update(UpdateSeriesRequest $request, int $series): JsonResponse
    {
        $series = Series::on('tenant')->findOrFail($series);

        // Vérification avant désactivation: pas de classes dans l'année active
        if ($request->has('is_active') && ! $request->boolean('is_active') && $series->is_active) {
            $hasClasses = $this->seriesHasClassesInActiveYear($series);
            if ($hasClasses) {
                return response()->json([
                    'message' => 'Impossible de désactiver cette série : des classes de cette série existent dans l\'année scolaire active.',
                ], 422);
            }
        }

        $series->update($request->validated());

        return response()->json([
            'message' => 'Série modifiée avec succès.',
            'data' => new SeriesResource($series),
        ]);
    }

    /**
     * Suppression interdite - retourner 403
     */
    public function destroy(int $series): JsonResponse
    {
        return response()->json([
            'message' => 'La suppression n\'est pas autorisée, utilisez la désactivation.',
        ], 403);
    }

    /**
     * Vérifie si une série a des classes dans l'année scolaire active
     */
    private function seriesHasClassesInActiveYear(Series $series): bool
    {
        if (! \Schema::connection('tenant')->hasTable('classes')) {
            return false;
        }

        return \DB::connection('tenant')
            ->table('classes')
            ->join('academic_years', 'classes.academic_year_id', '=', 'academic_years.id')
            ->where('classes.series_id', $series->id)
            ->where('academic_years.is_active', true)
            ->whereNull('classes.deleted_at')
            ->exists();
    }
}
