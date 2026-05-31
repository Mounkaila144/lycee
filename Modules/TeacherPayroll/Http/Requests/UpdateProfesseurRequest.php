<?php

namespace Modules\TeacherPayroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\TeacherPayroll\Entities\Professeur;

class UpdateProfesseurRequest extends FormRequest
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
            'user_id' => ['nullable', 'integer'],
            'matricule' => ['nullable', 'string', 'max:50'],
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'prenom' => ['sometimes', 'required', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'sexe' => ['nullable', 'in:M,F'],
            'date_naissance' => ['nullable', 'date'],
            'statut_professeur' => ['sometimes', 'required', 'in:'.implode(',', Professeur::STATUTS)],
            'specialite' => ['nullable', 'string', 'max:255'],
            'niveau_enseignement' => ['nullable', 'string', 'max:255'],
            'diplome' => ['nullable', 'string', 'max:255'],
            'numero_piece_identite' => ['nullable', 'string', 'max:255'],
            'date_recrutement' => ['nullable', 'date'],
            'actif' => ['nullable', 'boolean'],
        ];
    }
}
