<?php

namespace Modules\StructureAcademique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicYearRequest extends FormRequest
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
     */
    public function rules(): array
    {
        $academicYearId = $this->route('academicYear');

        return [
            'name' => ['sometimes', 'string', 'max:50', Rule::unique('tenant.academic_years', 'name')->ignore($academicYearId)],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
        ];
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Cette année scolaire existe déjà.',
            'end_date.after' => 'La date de fin doit être postérieure à la date de début.',
        ];
    }
}
