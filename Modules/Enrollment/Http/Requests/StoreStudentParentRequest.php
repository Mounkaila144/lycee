<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Enrollment\Http\Controllers\Admin\StudentParentController;

class StoreStudentParentRequest extends FormRequest
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
            'firstname' => ['required', 'string', 'max:100'],
            'lastname' => ['required', 'string', 'max:100'],
            'relationship' => ['required', 'string', Rule::in(StudentParentController::RELATIONSHIPS)],
            'phone' => ['required', 'string', 'max:30'],
            'phone_secondary' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'profession' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_primary_contact' => ['sometimes', 'boolean'],
            'is_financial_responsible' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'firstname.required' => 'Le prénom du parent est obligatoire.',
            'lastname.required' => 'Le nom du parent est obligatoire.',
            'relationship.required' => 'Le lien de parenté est obligatoire.',
            'relationship.in' => 'Le lien de parenté doit être Père, Mère, Tuteur, Tutrice ou Autre.',
            'phone.required' => 'Le téléphone du parent est obligatoire.',
            'phone.max' => 'Le téléphone ne peut pas dépasser 30 caractères.',
            'email.email' => "L'adresse e-mail est invalide.",
        ];
    }
}
