<?php

namespace Modules\StructureAcademique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSemesterRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', Rule::exists('tenant.academic_years', 'id')],
            'name' => [
                'required',
                Rule::in(['S1', 'S2']),
                Rule::unique('tenant.semesters', 'name')
                    ->where('academic_year_id', $this->input('academic_year_id')),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }

    /**
     * Messages d'erreur personnalisés
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'academic_year_id.required' => 'L\'année scolaire est obligatoire.',
            'academic_year_id.exists' => 'L\'année scolaire sélectionnée n\'existe pas.',
            'name.required' => 'Le nom du semestre est obligatoire.',
            'name.in' => 'Le semestre doit être S1 ou S2.',
            'name.unique' => 'Ce semestre existe déjà pour cette année scolaire.',
            'start_date.required' => 'La date de début est obligatoire.',
            'end_date.required' => 'La date de fin est obligatoire.',
            'end_date.after' => 'La date de fin doit être postérieure à la date de début.',
        ];
    }
}
