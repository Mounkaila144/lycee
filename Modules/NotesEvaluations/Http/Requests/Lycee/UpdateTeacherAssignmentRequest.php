<?php

namespace Modules\NotesEvaluations\Http\Requests\Lycee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherAssignmentRequest extends FormRequest
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
        ];
    }
}
