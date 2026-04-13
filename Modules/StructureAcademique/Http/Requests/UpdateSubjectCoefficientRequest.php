<?php

namespace Modules\StructureAcademique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubjectCoefficientRequest extends FormRequest
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
        return [
            'coefficient' => ['sometimes', 'numeric', 'min:1', 'max:8'],
            'hours_per_week' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            'coefficient.min' => 'Le coefficient doit être au minimum 1.',
            'coefficient.max' => 'Le coefficient ne peut pas dépasser 8.',
        ];
    }
}
