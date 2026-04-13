<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handle authorization via middleware/policies
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:5120', // 5 MB max
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
            'file.required' => 'Le fichier CSV est obligatoire',
            'file.file' => 'Le fichier doit être un fichier valide',
            'file.mimes' => 'Le fichier doit être au format CSV',
            'file.max' => 'Le fichier ne doit pas dépasser 5 MB',
            'programme_id.integer' => 'L\'identifiant du programme doit être un nombre entier',
            'programme_id.exists' => 'Le programme sélectionné n\'existe pas',
        ];
    }
}
