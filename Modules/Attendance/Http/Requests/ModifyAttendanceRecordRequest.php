<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Correction d'un enregistrement de présence (séance clôturée comprise) : motif obligatoire.
 */
class ModifyAttendanceRecordRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Le nouveau statut de présence est obligatoire.',
            'status.in' => 'Le statut de présence est invalide.',
            'reason.required' => 'Le motif de la correction est obligatoire.',
            'reason.string' => 'Le motif de la correction est invalide.',
            'reason.max' => 'Le motif de la correction ne doit pas dépasser 500 caractères.',
        ];
    }
}
