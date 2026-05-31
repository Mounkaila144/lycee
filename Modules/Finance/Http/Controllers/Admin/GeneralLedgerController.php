<?php

namespace Modules\Finance\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Finance\Services\GeneralLedgerService;

/**
 * Comptabilité générale : résultat consolidé (recettes − sorties).
 */
class GeneralLedgerController extends Controller
{
    public function __construct(private GeneralLedgerService $service) {}

    public function resultat(Request $request): JsonResponse
    {
        $from = $request->input('date_debut', Carbon::now()->startOfYear()->toDateString());
        $to = $request->input('date_fin', Carbon::now()->endOfYear()->toDateString());

        return response()->json(['data' => $this->service->resultat($from, $to)]);
    }
}
