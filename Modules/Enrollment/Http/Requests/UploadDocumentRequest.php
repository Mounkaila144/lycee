<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handle authorization via middleware/policies
    }

    public function rules(): array
    {
        return [
            'type' => [
                'required',
                Rule::in([
                    'certificat_naissance',
                    'releve_baccalaureat',
                    'photo_identite',
                    'cni_passeport',
                    'autre',
                ]),
            ],
            'file' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:2048', // Max 2MB
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Le type de document est obligatoire',
            'type.in' => 'Le type de document est invalide',
            'file.required' => 'Le fichier est obligatoire',
            'file.file' => 'Le fichier uploadé est invalide',
            'file.mimes' => 'Le fichier doit être au format PDF, JPG, JPEG ou PNG',
            'file.max' => 'Le fichier ne doit pas dépasser 2 MB',
            'description.max' => 'La description ne doit pas dépasser 500 caractères',
        ];
    }
}
