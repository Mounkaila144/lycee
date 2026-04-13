<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateExemptionRequest extends FormRequest
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
            'decision' => ['required', 'string', 'in:Approved,Partially_Approved,Rejected'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'grade' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'rejection_reason' => ['required_if:decision,Rejected', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'La décision est obligatoire.',
            'decision.in' => 'La décision doit être Approved, Partially_Approved ou Rejected.',
            'rejection_reason.required_if' => 'Le motif de rejet est obligatoire en cas de rejet.',
            'grade.min' => 'La note doit être positive.',
            'grade.max' => 'La note ne peut pas dépasser 20.',
        ];
    }
}
