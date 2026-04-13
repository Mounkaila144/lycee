<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateReenrollmentRequest extends FormRequest
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
            'campaign_id' => ['required', 'exists:reenrollment_campaigns,id'],
            'student_id' => ['required', 'exists:students,id'],
            'target_program_id' => ['sometimes', 'exists:programmes,id'],
            'is_redoing' => ['boolean'],
            'is_reorientation' => ['boolean'],
            'personal_data_updates' => ['nullable', 'array'],
            'personal_data_updates.email' => ['sometimes', 'email'],
            'personal_data_updates.phone' => ['sometimes', 'string', 'max:20'],
            'personal_data_updates.mobile' => ['sometimes', 'string', 'max:20'],
            'personal_data_updates.address' => ['sometimes', 'string', 'max:255'],
            'personal_data_updates.city' => ['sometimes', 'string', 'max:100'],
            'personal_data_updates.country' => ['sometimes', 'string', 'max:100'],
            'uploaded_documents' => ['nullable', 'array'],
            'has_accepted_rules' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'campaign_id.required' => 'La campagne de réinscription est obligatoire.',
            'campaign_id.exists' => 'La campagne spécifiée n\'existe pas.',
            'student_id.required' => 'L\'étudiant est obligatoire.',
            'student_id.exists' => 'L\'étudiant spécifié n\'existe pas.',
            'target_program_id.exists' => 'Le programme cible n\'existe pas.',
        ];
    }
}
