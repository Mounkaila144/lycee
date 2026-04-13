<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'birthdate' => ['required', 'date', 'before:today'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'origin_institution' => ['required', 'string', 'max:255'],
            'origin_program' => ['required', 'string', 'max:255'],
            'origin_level' => ['required', 'string', 'in:L1,L2,L3,M1,M2'],
            'target_program_id' => ['required', 'exists:programmes,id'],
            'target_level' => ['required', 'string', 'in:L1,L2,L3,M1,M2'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'transfer_reason' => ['required', 'string', 'min:50', 'max:2000'],
            'total_ects_claimed' => ['nullable', 'integer', 'min:0', 'max:300'],
            'documents' => ['nullable', 'array'],
            'documents.transcript' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'documents.certificate' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'documents.attestation' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'firstname.required' => 'Le prénom est obligatoire.',
            'lastname.required' => 'Le nom est obligatoire.',
            'birthdate.required' => 'La date de naissance est obligatoire.',
            'birthdate.before' => 'La date de naissance doit être dans le passé.',
            'email.required' => 'L\'email est obligatoire.',
            'email.email' => 'L\'email doit être valide.',
            'origin_institution.required' => 'L\'établissement d\'origine est obligatoire.',
            'origin_program.required' => 'Le programme d\'origine est obligatoire.',
            'origin_level.required' => 'Le niveau d\'origine est obligatoire.',
            'target_program_id.required' => 'Le programme cible est obligatoire.',
            'target_program_id.exists' => 'Le programme cible n\'existe pas.',
            'target_level.required' => 'Le niveau cible est obligatoire.',
            'academic_year_id.required' => 'L\'année académique est obligatoire.',
            'transfer_reason.required' => 'Le motif du transfert est obligatoire.',
            'transfer_reason.min' => 'Le motif doit contenir au moins 50 caractères.',
            'documents.*.mimes' => 'Les documents doivent être au format PDF.',
            'documents.*.max' => 'Les documents ne doivent pas dépasser 5MB.',
        ];
    }
}
