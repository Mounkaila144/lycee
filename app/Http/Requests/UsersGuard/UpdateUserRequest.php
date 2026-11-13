<?php

namespace App\Http\Requests\UsersGuard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\UsersGuard\Entities\TenantUser;

class UpdateUserRequest extends FormRequest
{
    /**
     * A non-Administrator (Manager) cannot modify an Administrator account,
     * grant the Administrator role, nor assign direct permissions.
     */
    public function authorize(): bool
    {
        $actingUser = $this->user();

        if ($actingUser && method_exists($actingUser, 'hasRole') && $actingUser->hasRole('Administrator')) {
            return true;
        }

        $targetUser = TenantUser::find($this->route('user'));

        if ($targetUser && $targetUser->hasRole('Administrator')) {
            return false;
        }

        return ! in_array('Administrator', (array) $this->input('roles', []), true)
            && empty($this->input('permissions'));
    }

    public function rules(): array
    {
        $userId = $this->route('user');

        return [
            'username' => ['sometimes', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'string', 'min:8'],
            'firstname' => ['sometimes', 'string', 'max:255'],
            'lastname' => ['sometimes', 'string', 'max:255'],
            'application' => ['sometimes', 'in:admin,frontend'],
            'sex' => ['nullable', 'in:M,F,Other'],
            'phone' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'Ce nom d\'utilisateur existe déjà.',
            'email.email' => 'L\'email doit être valide.',
            'email.unique' => 'Cet email existe déjà.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'application.in' => 'Le type d\'application doit être admin ou frontend.',
        ];
    }
}
