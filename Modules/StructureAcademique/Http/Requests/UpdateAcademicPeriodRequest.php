<?php

namespace Modules\StructureAcademique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAcademicPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Géré par middleware/policies
    }

    public function rules(): array
    {
        return [
            'semester_id' => ['sometimes', 'integer', 'exists:semesters,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'in:Jour férié,Vacances,Session examens,Inscription pédagogique,Autre'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'semester_id.exists' => 'Le semestre sélectionné n\'existe pas.',
            'type.in' => 'Le type doit être : Jour férié, Vacances, Session examens, Inscription pédagogique ou Autre.',
            'end_date.after_or_equal' => 'La date de fin doit être après ou égale à la date de début.',
        ];
    }
}
