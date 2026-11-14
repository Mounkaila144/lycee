<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStudentClassRequest extends FormRequest
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
            'classe_id' => ['required', 'integer', Rule::exists('tenant.classes', 'id')->whereNull('deleted_at')],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'classe_id.required' => 'La nouvelle classe est obligatoire.',
            'classe_id.exists' => 'La nouvelle classe est introuvable.',
            'reason.max' => 'Le motif ne peut pas dépasser 255 caractères.',
        ];
    }
}
