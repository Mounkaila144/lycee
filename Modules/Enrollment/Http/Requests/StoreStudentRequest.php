<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'birthdate' => ['required', 'date', 'before:today'],
            'birthplace' => ['nullable', 'string', 'max:255'],
            'sex' => ['required', Rule::in(['M', 'F'])],
            'nationality' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('tenant.students', 'email')],
            'mobile' => ['required', 'string', 'max:30', 'regex:/^[+]?[0-9\s\-\(\)]+$/'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[+]?[0-9\s\-\(\)]+$/'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'quarter' => ['nullable', 'string', 'max:255'],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'health_notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'force' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'firstname.required' => 'Le prénom est obligatoire.',
            'lastname.required' => 'Le nom est obligatoire.',
            'birthdate.required' => 'La date de naissance est obligatoire.',
            'birthdate.date' => 'La date de naissance doit être une date valide.',
            'birthdate.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'sex.required' => 'Le sexe est obligatoire.',
            'sex.in' => 'Le sexe doit être M ou F.',
            'email.required' => 'L\'email est obligatoire.',
            'email.email' => 'Le format de l\'email est invalide.',
            'email.unique' => 'Un élève avec cet email existe déjà.',
            'mobile.required' => 'Le téléphone mobile est obligatoire.',
            'mobile.regex' => 'Le format du mobile est invalide.',
            'phone.regex' => 'Le format du téléphone est invalide.',
            'blood_group.in' => 'Le groupe sanguin doit être A+, A-, B+, B-, AB+, AB-, O+ ou O-.',
            'photo.image' => 'Le fichier doit être une image.',
            'photo.mimes' => 'La photo doit être au format jpg, jpeg ou png.',
            'photo.max' => 'La photo ne doit pas dépasser 2 Mo.',
        ];
    }
}
