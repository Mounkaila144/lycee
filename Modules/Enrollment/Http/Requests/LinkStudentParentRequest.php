<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkStudentParentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['required', 'integer', Rule::exists('tenant.parents', 'id')->whereNull('deleted_at')],
            'is_primary_contact' => ['sometimes', 'boolean'],
            'is_financial_responsible' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.required' => 'Le parent est obligatoire.',
            'parent_id.exists' => 'Le parent est introuvable.',
        ];
    }
}
