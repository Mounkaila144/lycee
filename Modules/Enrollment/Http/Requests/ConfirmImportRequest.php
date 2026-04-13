<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handle authorization via middleware/policies
    }

    public function rules(): array
    {
        // Basic structure validation - allow invalid rows to pass through
        // The service will only import rows with is_valid=true
        return [
            'rows' => [
                'required',
                'array',
                'min:1',
            ],
            'rows.*.row_number' => [
                'required',
                'integer',
                'min:1',
            ],
            'rows.*.nom' => [
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.prenom' => [
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.email' => [
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.date_naissance' => [
                'nullable',
                'string',
            ],
            'rows.*.sexe' => [
                'nullable',
                'string',
            ],
            'rows.*.is_valid' => [
                'required',
                'boolean',
            ],
            'rows.*.errors' => [
                'nullable',
                'array',
            ],
            'rows.*.telephone' => [
                'nullable',
                'string',
                'max:20',
            ],
            'rows.*.mobile' => [
                'nullable',
                'string',
                'max:20',
            ],
            'rows.*.adresse' => [
                'nullable',
                'string',
            ],
            'rows.*.ville' => [
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.pays' => [
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.nationalite' => [
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.lieu_naissance' => [
                'nullable',
                'string',
                'max:255',
            ],
            'rows.*.programme' => [
                'nullable',
                'string',
                'max:50',
            ],
            'programme_id' => [
                'nullable',
                'integer',
                'exists:tenant.programmes,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'rows.required' => 'Les données à importer sont obligatoires',
            'rows.array' => 'Les données doivent être un tableau',
            'rows.min' => 'Au moins une ligne de données est requise',
            'rows.*.nom.required' => 'Le nom est obligatoire pour chaque ligne',
            'rows.*.prenom.required' => 'Le prénom est obligatoire pour chaque ligne',
            'rows.*.email.required' => 'L\'email est obligatoire pour chaque ligne',
            'rows.*.email.email' => 'L\'email doit être valide pour chaque ligne',
            'rows.*.date_naissance.required' => 'La date de naissance est obligatoire pour chaque ligne',
            'rows.*.sexe.required' => 'Le sexe est obligatoire pour chaque ligne',
            'rows.*.sexe.in' => 'Le sexe doit être M, F ou O',
            'rows.*.is_valid.required' => 'Le statut de validation est obligatoire',
        ];
    }

    /**
     * Get only the valid rows
     */
    public function getValidRows(): array
    {
        return collect($this->validated()['rows'])
            ->filter(fn ($row) => $row['is_valid'] === true)
            ->values()
            ->toArray();
    }
}
