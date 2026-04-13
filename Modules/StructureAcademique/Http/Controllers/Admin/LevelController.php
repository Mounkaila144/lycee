<?php

namespace Modules\StructureAcademique\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\StructureAcademique\Entities\Level;
use Modules\StructureAcademique\Http\Resources\LevelResource;

class LevelController extends Controller
{
    /**
     * Liste des niveaux (filtre optionnel par cycle)
     */
    public function index(Request $request)
    {
        $levels = Level::on('tenant')
            ->with(['cycle'])
            ->when($request->cycle_id, fn ($q, $cycleId) => $q->where('cycle_id', $cycleId))
            ->orderBy('order_index')
            ->get();

        return LevelResource::collection($levels);
    }

    /**
     * Détails d'un niveau
     */
    public function show(int $level): LevelResource
    {
        $level = Level::on('tenant')
            ->with(['cycle'])
            ->findOrFail($level);

        return new LevelResource($level);
    }
}
