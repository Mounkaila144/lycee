<?php

namespace Modules\StructureAcademique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAcademicPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Géré par middleware/policies
    }

    public function rules(): array
    {
        return [
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:Jour férié,Vacances,Session examens,Inscription pédagogique,Autre'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'semester_id.required' => 'Le semestre est obligatoire.',
            'semester_id.exists' => 'Le semestre sélectionné n\'existe pas.',
            'name.required' => 'Le nom est obligatoire.',
            'type.required' => 'Le type est obligatoire.',
            'type.in' => 'Le type doit être : Jour férié, Vacances, Session examens, Inscription pédagogique ou Autre.',
            'start_date.required' => 'La date de début est obligatoire.',
            'end_date.required' => 'La date de fin est obligatoire.',
            'end_date.after_or_equal' => 'La date de fin doit être après ou égale à la date de début.',
        ];
    }
}
