<?php

namespace Modules\Finance\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Finance\Entities\CashierCloseRecord;
use Modules\Finance\Entities\Payment;

/**
 * Story Caissier 05 — Clôture journalière de caisse.
 *
 * Ownership : un Caissier ne voit que SES propres clôtures (filtre cashier_user_id).
 * Admin et Comptable voient toutes les clôtures (pour rapprochement).
 *
 * Les totaux « système » sont calculés côté serveur à partir des paiements
 * enregistrés par le caissier connecté à la date clôturée : le caissier ne
 * saisit que l'espèce réellement comptée dans sa caisse.
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
     * Totaux système (par méthode) des encaissements du caissier connecté pour une date.
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $closeDate = Carbon::parse($validated['date'] ?? now()->toDateString());
        $userId = $request->user()->id;

        return response()->json([
            'data' => [
                'close_date' => $closeDate->toDateString(),
                'already_closed' => CashierCloseRecord::query()
                    ->where('cashier_user_id', $userId)
                    ->whereDate('close_date', $closeDate)
                    ->exists(),
                ...$this->systemTotals($userId, $closeDate),
            ],
        ]);
    }

    /**
     * Enregistre une clôture caisse pour le caissier connecté.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'close_date' => ['required', 'date', 'before_or_equal:today'],
            'total_cash_declared' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $closeDate = Carbon::parse($validated['close_date']);

        $alreadyClosed = CashierCloseRecord::query()
            ->where('cashier_user_id', $user->id)
            ->whereDate('close_date', $closeDate)
            ->exists();

        if ($alreadyClosed) {
            return response()->json([
                'message' => 'La caisse a déjà été clôturée pour cette date.',
                'errors' => ['close_date' => ['La caisse a déjà été clôturée pour cette date.']],
            ], 422);
        }

        $totals = $this->systemTotals($user->id, $closeDate);
        $variance = round((float) $validated['total_cash_declared'] - $totals['total_cash_system'], 2);

        $record = CashierCloseRecord::create([
            'cashier_user_id' => $user->id,
            'close_date' => $closeDate->toDateString(),
            'total_cash_declared' => $validated['total_cash_declared'],
            'total_cash_system' => $totals['total_cash_system'],
            'total_cheque' => $totals['total_cheque'],
            'total_mobile_money' => $totals['total_mobile_money'],
            'total_card' => $totals['total_card'],
            'total_transfer' => $totals['total_transfer'],
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

    /**
     * Sommes des paiements enregistrés par l'utilisateur à la date donnée, par méthode.
     *
     * @return array{total_cash_system: float, total_cheque: float, total_mobile_money: float, total_card: float, total_transfer: float, total_online: float, payments_count: int}
     */
    private function systemTotals(int $userId, Carbon $date): array
    {
        $payments = Payment::on('tenant')
            ->where('recorded_by', $userId)
            ->whereDate('payment_date', $date)
            ->get(['payment_method', 'amount']);

        $sumFor = fn (string $method): float => round(
            (float) $payments->where('payment_method', $method)->sum(fn (Payment $payment): float => (float) $payment->amount),
            2
        );

        return [
            'total_cash_system' => $sumFor('cash'),
            'total_cheque' => $sumFor('check'),
            'total_mobile_money' => $sumFor('mobile_money'),
            'total_card' => $sumFor('card'),
            'total_transfer' => $sumFor('bank_transfer'),
            'total_online' => $sumFor('online'),
            'payments_count' => $payments->count(),
        ];
    }
}
