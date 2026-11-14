<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkRecordAttendanceRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(['present', 'absent', 'late', 'excused'])],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Le statut de présence est obligatoire.',
            'status.in' => 'Le statut de présence est invalide.',
            'student_ids.array' => 'La liste des élèves est invalide.',
            'student_ids.*.integer' => 'Identifiant d\'élève invalide.',
        ];
    }
}
