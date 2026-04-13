<?php

namespace App\Http\Requests\UsersGuard;

use Illuminate\Foundation\Http\FormRequest;

class ManagePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', 'exists:permissions,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'permissions.required' => 'Les permissions sont requises.',
            'permissions.array' => 'Les permissions doivent être un tableau.',
            'permissions.min' => 'Au moins une permission est requise.',
            'permissions.*.exists' => 'Une ou plusieurs permissions n\'existent pas.',
        ];
    }
}
