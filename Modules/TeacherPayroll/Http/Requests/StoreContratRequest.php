<?php

namespace Modules\TeacherPayroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\TeacherPayroll\Entities\ContratProfesseur;

class StoreContratRequest extends FormRequest
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
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'type_contrat' => ['nullable', 'string', 'max:255'],
            'mode_paiement' => ['required', 'in:'.implode(',', ContratProfesseur::MODES_PAIEMENT)],
            'salaire_base' => ['nullable', 'numeric', 'min:0'],
            'taux_horaire' => ['nullable', 'numeric', 'min:0'],
            'taux_heure_supplementaire' => ['nullable', 'numeric', 'min:0'],
            'montant_forfait' => ['nullable', 'numeric', 'min:0'],
            'volume_horaire_mensuel_prevu' => ['nullable', 'numeric', 'min:0'],
            'volume_horaire_hebdomadaire_prevu' => ['nullable', 'numeric', 'min:0'],
            'paiement_pendant_vacances' => ['nullable', 'boolean'],
            'devise' => ['nullable', 'string', 'max:10'],
            'statut' => ['nullable', 'in:'.implode(',', ContratProfesseur::STATUTS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mode_paiement.in' => 'Le mode de paiement doit être MENSUEL, HORAIRE, HYBRIDE ou FORFAIT.',
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}
