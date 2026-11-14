<?php

namespace Modules\Payroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Payroll\Services\PayrollSettingsService;

/**
 * Simulation d'un bulletin à partir d'un salaire brut (aucune écriture).
 */
class SimulatePayrollRequest extends FormRequest
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
            'gross_salary' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'date' => ['nullable', 'date'],
        ], PayrollSettingsService::validationRules('settings.', false));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'gross_salary.required' => 'Le salaire brut est obligatoire.',
            'gross_salary.numeric' => 'Le salaire brut doit être un nombre.',
            'gross_salary.min' => 'Le salaire brut ne peut pas être négatif.',
            'gross_salary.max' => 'Le salaire brut est trop élevé.',
            'date.date' => "La date n'est pas valide.",
        ], PayrollSettingsService::validationMessages('settings.'));
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! is_array($this->input('settings'))) {
                    return;
                }

                foreach (PayrollSettingsService::structuralErrors($this->input('settings'), 'settings.') as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            },
        ];
    }
}
