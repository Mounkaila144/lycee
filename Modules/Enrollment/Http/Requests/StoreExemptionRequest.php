<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExemptionRequest extends FormRequest
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
            'student_id' => ['required', 'exists:students,id'],
            'module_id' => ['required', 'exists:modules,id'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'exemption_type' => ['required', 'string', 'in:Full,Partial,Exemption'],
            'reason_category' => ['required', 'string', 'in:VAE,Prior_Training,Professional_Certification,Special_Situation,Double_Degree,Other'],
            'reason_details' => ['required', 'string', 'min:100', 'max:2000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:pdf', 'max:5120'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'L\'étudiant est obligatoire.',
            'student_id.exists' => 'L\'étudiant spécifié n\'existe pas.',
            'module_id.required' => 'Le module est obligatoire.',
            'module_id.exists' => 'Le module spécifié n\'existe pas.',
            'academic_year_id.required' => 'L\'année académique est obligatoire.',
            'exemption_type.required' => 'Le type de dispense est obligatoire.',
            'exemption_type.in' => 'Le type de dispense doit être Full, Partial ou Exemption.',
            'reason_category.required' => 'La catégorie du motif est obligatoire.',
            'reason_category.in' => 'La catégorie du motif n\'est pas valide.',
            'reason_details.required' => 'Les détails du motif sont obligatoires.',
            'reason_details.min' => 'Les détails du motif doivent contenir au moins 100 caractères.',
            'documents.*.mimes' => 'Les documents doivent être au format PDF.',
            'documents.*.max' => 'Les documents ne doivent pas dépasser 5MB.',
        ];
    }
}
