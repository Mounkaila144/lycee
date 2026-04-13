<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handle authorization via middleware/policies
    }

    public function rules(): array
    {
        return [
            // Required personal information
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'birthdate' => [
                'required',
                'date',
                'before:'.now()->subYears(9)->format('Y-m-d'),  // Must be at least 9 years old (collège)
                'after:'.now()->subYears(25)->format('Y-m-d'),  // Maximum 25 years old
            ],
            'birthplace' => ['nullable', 'string', 'max:255'],
            'sex' => ['required', Rule::in(['M', 'F', 'O'])],
            'nationality' => ['required', 'string', 'max:255'],

            // Contact (email must be unique)
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('tenant.students', 'email'),
            ],
            'phone' => ['nullable', 'regex:/^\+\d{10,15}$/'],
            'mobile' => ['required', 'regex:/^\+\d{10,15}$/'],

            // Address
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],

            // Photo
            'photo' => ['nullable', 'image', 'max:2048'], // Max 2MB

            // Emergency contact
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'regex:/^\+\d{10,15}$/'],

            // Programme for matricule generation (optional - LMD legacy)
            'programme_id' => [
                'nullable',
                'integer',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'firstname.required' => 'Le prénom est obligatoire',
            'lastname.required' => 'Le nom de famille est obligatoire',
            'birthdate.required' => 'La date de naissance est obligatoire',
            'birthdate.before' => 'L\'élève doit avoir au moins 9 ans',
            'birthdate.after' => 'L\'élève ne peut pas avoir plus de 25 ans',
            'sex.required' => 'Le sexe est obligatoire',
            'sex.in' => 'Le sexe doit être M (Masculin), F (Féminin) ou O (Autre)',
            'nationality.required' => 'La nationalité est obligatoire',
            'email.required' => 'L\'adresse email est obligatoire',
            'email.email' => 'L\'adresse email doit être valide',
            'email.unique' => 'Cette adresse email est déjà utilisée par un autre étudiant',
            'mobile.required' => 'Le numéro de téléphone mobile est obligatoire',
            'mobile.regex' => 'Le numéro de téléphone doit être au format international (+227...)',
            'phone.regex' => 'Le numéro de téléphone doit être au format international (+227...)',
            'emergency_contact_phone.regex' => 'Le numéro de téléphone doit être au format international (+227...)',
            'country.required' => 'Le pays est obligatoire',
            'photo.image' => 'Le fichier doit être une image',
            'photo.max' => 'La photo ne doit pas dépasser 2 MB',
            'programme_id.integer' => 'L\'identifiant du programme doit être un nombre entier',
        ];
    }

    /**
     * Handle file uploads after validation
     */
    protected function passedValidation(): void
    {
        if ($this->hasFile('photo')) {
            $path = $this->file('photo')->store('students/photos', 'tenant');
            $this->merge(['photo' => $path]);
        }
    }
}
