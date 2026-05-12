<?php

namespace Modules\Finance\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Story Comptable 03 — Rapprochement bancaire.
 *
 * 4 tables : bank_accounts, bank_transactions, payment_bank_transaction_matches,
 * reconciliation_periods. Accès strictement Comptable/Administrator.
 *
 * Endpoints scaffold : la logique métier (matching auto, génération de variances)
 * est à implémenter dans des Services dédiés. Ce scaffold fournit les CRUD basiques.
 */
class BankReconciliationController extends Controller
{
    public function listAccounts(): JsonResponse
    {
        return response()->json([
            'data' => DB::connection('tenant')->table('bank_accounts')->orderBy('name')->get(),
        ]);
    }

    public function createAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'opening_balance' => ['nullable', 'numeric'],
        ]);

        $id = DB::connection('tenant')->table('bank_accounts')->insertGetId([
            'name' => $validated['name'],
            'iban' => $validated['iban'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'currency' => $validated['currency'] ?? 'XOF',
            'opening_balance' => $validated['opening_balance'] ?? 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $id, ...$validated]], 201);
    }

    public function listTransactions(Request $request): JsonResponse
    {
        $query = DB::connection('tenant')->table('bank_transactions');

        if ($accountId = $request->query('bank_account_id')) {
            $query->where('bank_account_id', $accountId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->orderByDesc('transaction_date')->limit(200)->get()]);
    }

    public function listPeriods(): JsonResponse
    {
        return response()->json([
            'data' => DB::connection('tenant')->table('reconciliation_periods')->orderByDesc('period_start')->get(),
        ]);
    }
}
