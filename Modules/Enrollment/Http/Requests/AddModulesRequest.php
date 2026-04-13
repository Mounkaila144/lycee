<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handle authorization via middleware/policies
    }

    public function rules(): array
    {
        return [
            'module_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'module_ids.*' => [
                'integer',
                'exists:tenant.modules,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'module_ids.required' => 'Au moins un module est requis',
            'module_ids.array' => 'Les modules doivent être un tableau',
            'module_ids.min' => 'Au moins un module est requis',
            'module_ids.*.exists' => 'Un des modules sélectionnés n\'existe pas',
        ];
    }
}
