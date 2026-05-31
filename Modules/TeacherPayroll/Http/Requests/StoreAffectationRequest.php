<?php

namespace Modules\TeacherPayroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAffectationRequest extends FormRequest
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
            'professeur_id' => ['required', 'integer'],
            'classe_id' => ['required', 'integer'],
            'matiere_id' => ['required', 'integer'],
            'annee_scolaire_id' => ['nullable', 'integer'],
            'volume_horaire_hebdomadaire' => ['nullable', 'numeric', 'min:0'],
            'coefficient' => ['nullable', 'numeric', 'min:0'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'actif' => ['nullable', 'boolean'],
        ];
    }
}
