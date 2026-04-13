<?php

namespace App\Http\Requests\UsersGuard;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'max:255', 'unique:tenants,id', 'alpha_dash'],
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:20'],
            'company_address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'domains' => ['required', 'array', 'min:1'],
            'domains.*.domain' => ['required', 'string', 'max:255', 'unique:domains,domain'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'L\'identifiant du tenant est requis.',
            'id.unique' => 'Cet identifiant existe déjà.',
            'id.alpha_dash' => 'L\'identifiant ne peut contenir que des lettres, chiffres, tirets et underscores.',
            'company_name.required' => 'Le nom de l\'entreprise est requis.',
            'company_email.email' => 'L\'email doit être valide.',
            'domains.required' => 'Au moins un domaine est requis.',
            'domains.min' => 'Au moins un domaine est requis.',
            'domains.*.domain.required' => 'Le nom de domaine est requis.',
            'domains.*.domain.unique' => 'Ce domaine existe déjà.',
        ];
    }
}
