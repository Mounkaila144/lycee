<?php

namespace Modules\Finance\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Finance\Entities\CashierCloseRecord;

/**
 * Story Caissier 05 — Clôture journalière de caisse.
 *
 * Ownership : un Caissier ne voit que SES propres clôtures (filtre cashier_user_id).
 * Admin et Comptable voient toutes les clôtures (pour rapprochement).
 */
class CashierCloseController extends Controller
{
    /**
     * Liste des clôtures du caissier connecté (ou toutes si Admin/Comptable).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = CashierCloseRecord::query();

        if ($user->hasRole('Caissier') && ! $user->hasAnyRole(['Administrator', 'Comptable'])) {
            $query->where('cashier_user_id', $user->id);
        }

        return response()->json([
            'data' => $query->orderByDesc('close_date')->get(),
        ]);
    }

    /**
     * Enregistre une clôture caisse pour le caissier connecté.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'close_date' => ['required', 'date'],
            'total_cash_declared' => ['required', 'numeric', 'min:0'],
            'total_cash_system' => ['required', 'numeric', 'min:0'],
            'total_cheque' => ['nullable', 'numeric', 'min:0'],
            'total_mobile_money' => ['nullable', 'numeric', 'min:0'],
            'total_card' => ['nullable', 'numeric', 'min:0'],
            'total_transfer' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $variance = $validated['total_cash_declared'] - $validated['total_cash_system'];

        $record = CashierCloseRecord::create([
            'cashier_user_id' => $user->id,
            'close_date' => $validated['close_date'],
            'total_cash_declared' => $validated['total_cash_declared'],
            'total_cash_system' => $validated['total_cash_system'],
            'total_cheque' => $validated['total_cheque'] ?? 0,
            'total_mobile_money' => $validated['total_mobile_money'] ?? 0,
            'total_card' => $validated['total_card'] ?? 0,
            'total_transfer' => $validated['total_transfer'] ?? 0,
            'variance' => $variance,
            'status' => abs($variance) > 0.01 ? 'variance_pending' : 'closed',
            'notes' => $validated['notes'] ?? null,
            'closed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Clôture de caisse enregistrée.',
            'data' => $record,
        ], 201);
    }
}
