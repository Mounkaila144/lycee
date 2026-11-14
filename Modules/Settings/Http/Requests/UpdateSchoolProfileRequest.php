<?php

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Settings\Services\SchoolProfileService;

class UpdateSchoolProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'short_name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'type' => ['sometimes', 'nullable', 'string', Rule::in(SchoolProfileService::TYPES)],
            'motto' => ['sometimes', 'nullable', 'string', 'max:191'],
            'official_header' => ['sometimes', 'nullable', 'string', 'max:500'],
            'region' => ['sometimes', 'nullable', 'string', 'max:150'],
            'inspection' => ['sometimes', 'nullable', 'string', 'max:150'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'po_box' => ['sometimes', 'nullable', 'string', 'max:50'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'phone_secondary' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'website' => ['sometimes', 'nullable', 'string', 'max:150'],
            'authorization_number' => ['sometimes', 'nullable', 'string', 'max:150'],
            'head_title' => ['sometimes', 'nullable', 'string', 'max:100'],
            'head_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'censor_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.max' => "Le nom de l'établissement ne doit pas dépasser 191 caractères.",
            'short_name.max' => 'Le sigle ne doit pas dépasser 50 caractères.',
            'type.in' => "Le type d'établissement doit être : ".implode(', ', SchoolProfileService::TYPES).'.',
            'official_header.max' => "L'en-tête officiel ne doit pas dépasser 500 caractères.",
            'email.email' => "L'adresse e-mail n'est pas valide.",
            '*.string' => 'Ce champ doit être un texte.',
            '*.max' => 'Ce champ est trop long.',
        ];
    }
}
