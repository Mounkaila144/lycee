<?php

namespace Modules\NotesEvaluations\Http\Requests\Lycee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkTeacherAssignmentRequest extends FormRequest
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
            'classe_id' => ['required', 'integer', Rule::exists('tenant.classes', 'id')->whereNull('deleted_at')],
            'items' => ['required', 'array', 'min:1'],
            'items.*.subject_id' => ['required', 'integer', 'distinct', Rule::exists('tenant.subjects', 'id')],
            'items.*.teacher_id' => ['nullable', 'integer', Rule::exists('tenant.users', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'classe_id.required' => 'La classe est obligatoire.',
            'classe_id.exists' => 'La classe sélectionnée est introuvable.',
            'items.required' => 'La liste des affectations est obligatoire.',
            'items.array' => 'La liste des affectations est invalide.',
            'items.min' => 'La liste des affectations est vide.',
            'items.*.subject_id.required' => 'La matière est obligatoire pour chaque ligne.',
            'items.*.subject_id.distinct' => 'Une matière apparaît plusieurs fois.',
            'items.*.subject_id.exists' => 'Une matière sélectionnée est introuvable.',
            'items.*.teacher_id.exists' => 'Un professeur sélectionné est introuvable.',
        ];
    }
}
