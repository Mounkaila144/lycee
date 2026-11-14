<?php

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadSchoolImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Veuillez choisir une image.',
            'file.image' => 'Le fichier doit être une image.',
            'file.mimes' => 'Formats acceptés : PNG, JPG ou WEBP.',
            'file.max' => "L'image ne doit pas dépasser 2 Mo.",
        ];
    }
}
