<?php

namespace Modules\StructureAcademique\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\StructureAcademique\Entities\Cycle;
use Modules\StructureAcademique\Http\Requests\UpdateCycleRequest;
use Modules\StructureAcademique\Http\Resources\CycleResource;

class CycleController extends Controller
{
    /**
     * Liste des cycles avec leurs niveaux
     */
    public function index(Request $request)
    {
        $cycles = Cycle::on('tenant')
            ->with(['levels'])
            ->orderBy('display_order')
            ->get();

        return CycleResource::collection($cycles);
    }

    /**
     * Détails d'un cycle
     */
    public function show(int $cycle): CycleResource
    {
        $cycle = Cycle::on('tenant')
            ->with(['levels'])
            ->findOrFail($cycle);

        return new CycleResource($cycle);
    }

    /**
     * Modifier un cycle (description et is_active uniquement)
     */
    public function update(UpdateCycleRequest $request, int $cycle): JsonResponse
    {
        $cycle = Cycle::on('tenant')->findOrFail($cycle);

        // Vérification avant désactivation: pas de classes dans l'année active
        if ($request->has('is_active') && ! $request->boolean('is_active') && $cycle->is_active) {
            $hasClasses = $this->cycleHasClassesInActiveYear($cycle);
            if ($hasClasses) {
                return response()->json([
                    'message' => 'Impossible de désactiver ce cycle : des classes existent pour ce cycle dans l\'année scolaire active.',
                ], 422);
            }
        }

        $cycle->update($request->validated());

        return response()->json([
            'message' => 'Cycle modifié avec succès.',
            'data' => new CycleResource($cycle->load('levels')),
        ]);
    }

    /**
     * Vérifie si un cycle a des classes dans l'année scolaire active
     */
    private function cycleHasClassesInActiveYear(Cycle $cycle): bool
    {
        // Check if classes table exists (may not exist yet if Story 2.3 not implemented)
        if (! \Schema::connection('tenant')->hasTable('classes')) {
            return false;
        }

        return \DB::connection('tenant')
            ->table('classes')
            ->join('levels', 'classes.level_id', '=', 'levels.id')
            ->join('academic_years', 'classes.academic_year_id', '=', 'academic_years.id')
            ->where('levels.cycle_id', $cycle->id)
            ->where('academic_years.is_active', true)
            ->whereNull('classes.deleted_at')
            ->exists();
    }
}
