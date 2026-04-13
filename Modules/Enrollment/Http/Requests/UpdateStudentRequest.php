<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handle authorization via middleware/policies
    }

    /**
     * Prepare the data for validation - Remove immutable fields if present
     */
    protected function prepareForValidation(): void
    {
        // Remove immutable fields from request to prevent updates
        $immutableFields = ['firstname', 'lastname', 'birthdate', 'matricule'];

        foreach ($immutableFields as $field) {
            if ($this->has($field)) {
                $this->request->remove($field);
            }
        }
    }

    public function rules(): array
    {
        $studentId = $this->route('student');

        return [
            // Personal information
            // IMMUTABLE FIELDS: firstname, lastname, birthdate, matricule cannot be updated
            // Only modifiable fields are listed below
            'birthplace' => ['nullable', 'string', 'max:255'],
            'sex' => ['sometimes', Rule::in(['M', 'F', 'O'])],
            'nationality' => ['sometimes', 'string', 'max:255'],

            // Contact
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('tenant.students', 'email')->ignore($studentId),
            ],
            'phone' => ['nullable', 'regex:/^\+\d{10,15}$/'],
            'mobile' => ['sometimes', 'regex:/^\+\d{10,15}$/'],

            // Address
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'string', 'max:255'],

            // Photo
            'photo' => ['nullable', 'image', 'max:2048'],

            // Status
            'status' => ['sometimes', Rule::in(['Actif', 'Suspendu', 'Exclu', 'Diplômé'])],

            // Emergency contact
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'regex:/^\+\d{10,15}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'sex.in' => 'Le sexe doit être M (Masculin), F (Féminin) ou O (Autre)',
            'email.email' => 'L\'adresse email doit être valide',
            'email.unique' => 'Cette adresse email est déjà utilisée par un autre étudiant',
            'mobile.regex' => 'Le numéro de téléphone doit être au format international (+227...)',
            'phone.regex' => 'Le numéro de téléphone doit être au format international (+227...)',
            'emergency_contact_phone.regex' => 'Le numéro de téléphone doit être au format international (+227...)',
            'photo.image' => 'Le fichier doit être une image',
            'photo.max' => 'La photo ne doit pas dépasser 2 MB',
            'status.in' => 'Le statut doit être: Actif, Suspendu, Exclu ou Diplômé',
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
