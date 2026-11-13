<?php

namespace App\Http\Requests\UsersGuard;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Only an Administrator may grant the Administrator role or direct permissions.
     * A Manager can create users but must not be able to escalate privileges.
     */
    public function authorize(): bool
    {
        $actingUser = $this->user();

        if ($actingUser && method_exists($actingUser, 'hasRole') && $actingUser->hasRole('Administrator')) {
            return true;
        }

        return ! in_array('Administrator', (array) $this->input('roles', []), true)
            && empty($this->input('permissions'));
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'application' => ['required', 'in:admin,frontend'],
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
            'username.required' => 'Le nom d\'utilisateur est requis.',
            'username.unique' => 'Ce nom d\'utilisateur existe déjà.',
            'email.required' => 'L\'email est requis.',
            'email.email' => 'L\'email doit être valide.',
            'email.unique' => 'Cet email existe déjà.',
            'password.required' => 'Le mot de passe est requis.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'firstname.required' => 'Le prénom est requis.',
            'lastname.required' => 'Le nom est requis.',
            'application.required' => 'Le type d\'application est requis.',
            'application.in' => 'Le type d\'application doit être admin ou frontend.',
        ];
    }
}
