<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Facturation en masse : cible (classe, niveau ou toute l'année), types de frais, échéance.
 */
class BulkBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', Rule::exists('tenant.academic_years', 'id')],
            'scope' => ['required', 'string', Rule::in(['classe', 'level', 'year'])],
            'classe_id' => ['required_if:scope,classe', 'nullable', 'integer', Rule::exists('tenant.classes', 'id')->whereNull('deleted_at')],
            'level_id' => ['required_if:scope,level', 'nullable', 'integer', Rule::exists('tenant.levels', 'id')],
            'fee_type_ids' => ['required', 'array', 'min:1'],
            'fee_type_ids.*' => ['integer', 'distinct', Rule::exists('tenant.fee_types', 'id')->whereNull('deleted_at')],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:'.($this->input('invoice_date') ?: 'today')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'academic_year_id.required' => 'L\'année scolaire est obligatoire.',
            'scope.in' => 'Cible invalide (classe, niveau ou année).',
            'classe_id.required_if' => 'Choisissez une classe.',
            'classe_id.exists' => 'Classe introuvable.',
            'level_id.required_if' => 'Choisissez un niveau.',
            'level_id.exists' => 'Niveau introuvable.',
            'fee_type_ids.required' => 'Choisissez au moins un type de frais.',
            'fee_type_ids.min' => 'Choisissez au moins un type de frais.',
            'fee_type_ids.*.exists' => 'Type de frais introuvable ou désactivé.',
            'due_date.required' => 'L\'échéance est obligatoire.',
            'due_date.after_or_equal' => 'L\'échéance doit être postérieure ou égale à la date de facture.',
        ];
    }
}
