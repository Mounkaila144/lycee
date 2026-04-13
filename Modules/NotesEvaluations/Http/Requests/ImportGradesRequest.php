<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
            'evaluation_id' => ['required', 'integer', 'exists:module_evaluation_configs,id'],
            'import_mode' => ['required', 'in:add,update,overwrite'],
            'async' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Le fichier est obligatoire.',
            'file.mimes' => 'Le fichier doit être au format Excel (.xlsx ou .xls).',
            'file.max' => 'Le fichier ne peut pas dépasser 5 Mo.',
            'evaluation_id.required' => "L'identifiant de l'évaluation est obligatoire.",
            'evaluation_id.exists' => "L'évaluation spécifiée n'existe pas.",
            'import_mode.required' => 'Le mode d\'import est obligatoire.',
            'import_mode.in' => 'Le mode d\'import doit être: add, update ou overwrite.',
        ];
    }
}
