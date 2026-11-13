<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitJustificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `student_id` désigne le compte utilisateur de l'élève (users.id) côté administration,
     * et la fiche élève (students.id) côté portail Parent / Élève.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['nullable', 'integer'],
            'absence_date_from' => ['required', 'date'],
            'absence_date_to' => ['required', 'date', 'after_or_equal:absence_date_from'],
            'type' => ['required', 'string', Rule::in(['medical', 'family', 'administrative', 'other'])],
            'reason' => ['required', 'string', 'max:1000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'absence_date_from.required' => 'La date de début d\'absence est obligatoire.',
            'absence_date_to.required' => 'La date de fin d\'absence est obligatoire.',
            'absence_date_to.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'type.in' => 'Le type de justificatif est invalide.',
            'reason.required' => 'Le motif est obligatoire.',
            'document.mimes' => 'Le document doit être un PDF ou une image (jpg, png).',
            'document.max' => 'Le document ne doit pas dépasser 5 Mo.',
        ];
    }
}
