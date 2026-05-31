<?php

namespace Modules\TeacherPayroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\TeacherPayroll\Entities\Professeur;

class StoreProfesseurRequest extends FormRequest
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
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'sexe' => ['nullable', 'in:M,F'],
            'date_naissance' => ['nullable', 'date'],
            'statut_professeur' => ['required', 'in:'.implode(',', Professeur::STATUTS)],
            'specialite' => ['nullable', 'string', 'max:255'],
            'niveau_enseignement' => ['nullable', 'string', 'max:255'],
            'diplome' => ['nullable', 'string', 'max:255'],
            'numero_piece_identite' => ['nullable', 'string', 'max:255'],
            'date_recrutement' => ['nullable', 'date'],
            'actif' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom du professeur est obligatoire.',
            'prenom.required' => 'Le prénom du professeur est obligatoire.',
            'statut_professeur.in' => 'Le statut du professeur est invalide.',
        ];
    }
}
