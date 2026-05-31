<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'integer', 'exists:tenant.expense_categories,id'],
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
            'paid_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:pending,paid'],
            'payment_method' => ['nullable', 'in:cash,check,bank_transfer,card,mobile_money,other'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_recurring' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expense_category_id.required' => 'La catégorie est obligatoire.',
            'expense_category_id.exists' => 'Catégorie de dépense invalide.',
            'label.required' => 'Le libellé est obligatoire.',
            'amount.required' => 'Le montant est obligatoire.',
        ];
    }
}
