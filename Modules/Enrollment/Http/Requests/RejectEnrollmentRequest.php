<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:20', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Le motif de rejet est obligatoire.',
            'rejection_reason.min' => 'Le motif de rejet doit contenir au moins 20 caractères.',
            'rejection_reason.max' => 'Le motif de rejet ne peut pas dépasser 1000 caractères.',
        ];
    }
}
