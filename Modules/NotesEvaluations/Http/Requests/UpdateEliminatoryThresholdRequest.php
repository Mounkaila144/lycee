<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEliminatoryThresholdRequest extends FormRequest
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
            'eliminatory_threshold' => ['required', 'numeric', 'min:5', 'max:20'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'eliminatory_threshold.required' => 'Le seuil éliminatoire est obligatoire.',
            'eliminatory_threshold.numeric' => 'Le seuil éliminatoire doit être un nombre.',
            'eliminatory_threshold.min' => 'Le seuil éliminatoire doit être au moins de 5.',
            'eliminatory_threshold.max' => 'Le seuil éliminatoire ne peut pas dépasser 20.',
        ];
    }
}
