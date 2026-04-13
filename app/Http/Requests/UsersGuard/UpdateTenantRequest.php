<?php

namespace App\Http\Requests\UsersGuard;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:20'],
            'company_address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'domains' => ['sometimes', 'array', 'min:1'],
            'domains.*.domain' => ['required_with:domains', 'string', 'max:255', 'unique:domains,domain'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.string' => 'Le nom de l\'entreprise doit être une chaîne de caractères.',
            'company_email.email' => 'L\'email doit être valide.',
            'domains.min' => 'Au moins un domaine est requis.',
            'domains.*.domain.required_with' => 'Le nom de domaine est requis.',
            'domains.*.domain.unique' => 'Ce domaine existe déjà.',
        ];
    }
}
