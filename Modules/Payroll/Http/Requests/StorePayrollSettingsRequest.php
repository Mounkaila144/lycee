<?php

namespace Modules\Payroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Payroll\Services\PayrollSettingsService;

/**
 * Nouvelle version des paramètres de paie (cotisations, barème, charges patronales).
 */
class StorePayrollSettingsRequest extends FormRequest
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
        return array_merge([
            'effective_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], PayrollSettingsService::validationRules('settings.'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'effective_date.required' => "La date d'effet est obligatoire.",
            'effective_date.date' => "La date d'effet n'est pas valide.",
            'settings.required' => 'Les paramètres de paie sont obligatoires.',
        ], PayrollSettingsService::validationMessages('settings.'));
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (PayrollSettingsService::structuralErrors((array) $this->input('settings', []), 'settings.') as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }
}
