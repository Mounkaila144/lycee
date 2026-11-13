<?php

namespace Modules\NotesEvaluations\Http\Requests\Lycee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherAssignmentRequest extends FormRequest
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
            'teacher_id' => ['required', 'integer', Rule::exists('tenant.users', 'id')],
            'classe_id' => ['required', 'integer', Rule::exists('tenant.classes', 'id')->whereNull('deleted_at')],
            'subject_id' => ['required', 'integer', Rule::exists('tenant.subjects', 'id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'teacher_id.required' => 'Le professeur est obligatoire.',
            'teacher_id.exists' => 'Le professeur sélectionné est introuvable.',
            'classe_id.required' => 'La classe est obligatoire.',
            'classe_id.exists' => 'La classe sélectionnée est introuvable.',
            'subject_id.required' => 'La matière est obligatoire.',
            'subject_id.exists' => 'La matière sélectionnée est introuvable.',
        ];
    }
}
