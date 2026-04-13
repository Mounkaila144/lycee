<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateEquivalenceRequest extends FormRequest
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
            'equivalence_type' => ['sometimes', 'string', 'in:Full,Partial,None,Exemption'],
            'equivalence_percentage' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'target_module_id' => ['nullable', 'exists:modules,id'],
            'granted_ects' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'granted_grade' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
