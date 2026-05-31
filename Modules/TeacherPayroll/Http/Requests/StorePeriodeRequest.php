<?php

namespace Modules\TeacherPayroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePeriodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'annee_scolaire_id' => ['nullable', 'integer'],
            'mois' => ['required', 'integer', 'min:1', 'max:12'],
            'annee' => ['required', 'integer', 'min:2000', 'max:2100'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'est_vacances' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mois.between' => 'Le mois doit être compris entre 1 et 12.',
        ];
    }
}
