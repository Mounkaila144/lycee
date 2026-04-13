<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'module_id' => ['required', 'integer', 'exists:modules,id'],
            'evaluation_id' => ['nullable', 'integer', 'exists:module_evaluation_configs,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'module_id.required' => "L'identifiant du module est obligatoire.",
            'module_id.exists' => "Le module spécifié n'existe pas.",
            'evaluation_id.exists' => "L'évaluation spécifiée n'existe pas.",
        ];
    }
}
