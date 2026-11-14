<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePortalAccountRequest extends FormRequest
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
            'username' => ['nullable', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9._-]+$/', Rule::unique('tenant.users', 'username')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.min' => "L'identifiant doit contenir au moins 3 caractères.",
            'username.max' => "L'identifiant ne peut pas dépasser 60 caractères.",
            'username.regex' => "L'identifiant ne peut contenir que des minuscules, chiffres, points, tirets et soulignés.",
            'username.unique' => 'Cet identifiant est déjà utilisé.',
        ];
    }
}
