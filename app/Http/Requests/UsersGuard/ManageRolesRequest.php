<?php

namespace App\Http\Requests\UsersGuard;

use Illuminate\Foundation\Http\FormRequest;

class ManageRolesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Géré par middleware/policies
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'roles.required' => 'Au moins un rôle est requis.',
            'roles.*.exists' => 'Le rôle ":input" n\'existe pas.',
        ];
    }
}
