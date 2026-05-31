<?php

namespace Modules\Finance\Http\Controllers\Admin;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Finance\Entities\Expense;
use Modules\Finance\Entities\ExpenseCategory;
use Modules\Finance\Http\Requests\StoreExpenseRequest;

class ExpenseController extends Controller
{
    // ---- Catégories -------------------------------------------------------

    public function indexCategories(Request $request): JsonResponse
    {
        $query = ExpenseCategory::on('tenant')->withCount('expenses');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:tenant.expense_categories,code'],
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:20'],
        ]);
        $data['is_active'] = true;

        $category = ExpenseCategory::on('tenant')->create($data);

        return response()->json(['message' => 'Catégorie créée', 'data' => $category], 201);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $category = ExpenseCategory::on('tenant')->findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update($data);

        return response()->json(['message' => 'Catégorie mise à jour', 'data' => $category->refresh()]);
    }

    // ---- Dépenses ---------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $query = Expense::on('tenant')->with('category');

        if ($categoryId = $request->input('expense_category_id')) {
            $query->where('expense_category_id', $categoryId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->input('date_debut')) {
            $query->whereDate('expense_date', '>=', $from);
        }
        if ($to = $request->input('date_fin')) {
            $query->whereDate('expense_date', '<=', $to);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('label', 'like', "%{$search}%")
                    ->orWhere('supplier', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        return response()->json(['data' => $query->orderBy('expense_date', 'desc')->paginate($request->input('per_page', 30))]);
    }

    /**
     * Détecte d'éventuelles dépenses similaires déjà saisies (même catégorie,
     * même mois) afin d'éviter les doublons (ex. payer 2× l'électricité).
     */
    public function checkDuplicate(Request $request): JsonResponse
    {
        $request->validate([
            'expense_category_id' => ['required', 'integer'],
            'expense_date' => ['required', 'date'],
            'amount' => ['nullable', 'numeric'],
        ]);

        $date = Carbon::parse($request->input('expense_date'));

        $query = Expense::on('tenant')->with('category')
            ->where('expense_category_id', $request->input('expense_category_id'))
            ->whereYear('expense_date', $date->year)
            ->whereMonth('expense_date', $date->month);

        if ($request->input('exclude_id')) {
            $query->where('id', '!=', $request->input('exclude_id'));
        }

        $matches = $query->orderBy('expense_date', 'desc')->get();

        return response()->json([
            'data' => [
                'has_duplicates' => $matches->isNotEmpty(),
                'matches' => $matches,
            ],
        ]);
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'paid';
        $data['payment_method'] = $data['payment_method'] ?? 'cash';
        $data['is_recurring'] = $data['is_recurring'] ?? false;

        if ($data['status'] === 'paid' && empty($data['paid_date'])) {
            $data['paid_date'] = $data['expense_date'];
        }

        $data['recorded_by'] = auth('tenant')->id();

        $expense = Expense::on('tenant')->create($data);

        return response()->json(['message' => 'Dépense enregistrée', 'data' => $expense->load('category')], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => Expense::on('tenant')->with('category')->findOrFail($id)]);
    }

    public function update(StoreExpenseRequest $request, int $id): JsonResponse
    {
        $expense = Expense::on('tenant')->findOrFail($id);

        $data = $request->validated();
        if (($data['status'] ?? $expense->status) === 'paid' && empty($data['paid_date']) && ! $expense->paid_date) {
            $data['paid_date'] = $data['expense_date'] ?? $expense->expense_date;
        }

        $expense->update($data);

        return response()->json(['message' => 'Dépense mise à jour', 'data' => $expense->refresh()->load('category')]);
    }

    public function markPaid(Request $request, int $id): JsonResponse
    {
        $expense = Expense::on('tenant')->findOrFail($id);
        $expense->update([
            'status' => 'paid',
            'paid_date' => $request->input('paid_date', now()->toDateString()),
            'payment_method' => $request->input('payment_method', $expense->payment_method),
        ]);

        return response()->json(['message' => 'Dépense marquée payée', 'data' => $expense->refresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        Expense::on('tenant')->findOrFail($id)->delete();

        return response()->json(['message' => 'Dépense supprimée'], 200);
    }
}
