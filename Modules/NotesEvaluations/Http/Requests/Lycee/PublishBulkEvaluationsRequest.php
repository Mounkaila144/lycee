<?php

namespace Modules\NotesEvaluations\Http\Requests\Lycee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublishBulkEvaluationsRequest extends FormRequest
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
            'semester_id' => ['required', 'integer', Rule::exists('tenant.semesters', 'id')],
            'classe_id' => ['nullable', 'integer', Rule::exists('tenant.classes', 'id')],
            'evaluation_ids' => ['nullable', 'array'],
            'evaluation_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'semester_id.required' => 'Le semestre est obligatoire.',
            'semester_id.exists' => 'Le semestre sélectionné est introuvable.',
            'classe_id.exists' => 'La classe sélectionnée est introuvable.',
            'evaluation_ids.array' => 'La liste des évaluations est invalide.',
        ];
    }
}
