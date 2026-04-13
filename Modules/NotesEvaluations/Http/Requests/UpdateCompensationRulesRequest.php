<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompensationRulesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'compensation_enabled' => ['sometimes', 'boolean'],
            'min_semester_average' => ['sometimes', 'numeric', 'min:0', 'max:20'],
            'min_compensable_grade' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'max_compensated_modules' => ['nullable', 'integer', 'min:0', 'max:20'],
            'allow_professional_module_compensation' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'min_semester_average.min' => 'La moyenne semestre minimale doit être au moins 0.',
            'min_semester_average.max' => 'La moyenne semestre minimale ne peut pas dépasser 20.',
            'min_compensable_grade.min' => 'La note minimale compensable doit être au moins 0.',
            'min_compensable_grade.max' => 'La note minimale compensable ne peut pas dépasser 20.',
            'max_compensated_modules.min' => 'Le nombre maximum de modules compensés doit être au moins 0.',
            'max_compensated_modules.max' => 'Le nombre maximum de modules compensés ne peut pas dépasser 20.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'compensation_enabled' => 'activation compensation',
            'min_semester_average' => 'moyenne semestre minimale',
            'min_compensable_grade' => 'note minimale compensable',
            'max_compensated_modules' => 'nombre max modules compensés',
            'allow_professional_module_compensation' => 'compensation modules professionnels',
        ];
    }
}
