<?php

namespace Modules\TeacherPayroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\TeacherPayroll\Entities\HeureCoursProfesseur;

class StoreHeureCoursRequest extends FormRequest
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
            'classe_id' => ['nullable', 'integer'],
            'matiere_id' => ['nullable', 'integer'],
            'annee_scolaire_id' => ['nullable', 'integer'],
            'date_cours' => ['required', 'date'],
            'heure_debut' => ['nullable', 'date_format:H:i'],
            'heure_fin' => ['nullable', 'date_format:H:i'],
            'nombre_heures' => ['required', 'numeric', 'min:0'],
            'statut' => ['nullable', 'in:'.implode(',', HeureCoursProfesseur::STATUTS)],
            'remplace_par_professeur_id' => ['nullable', 'integer'],
            'motif' => ['nullable', 'string', 'max:255'],
        ];
    }
}
