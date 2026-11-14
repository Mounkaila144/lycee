<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Enrollment\Entities\ClassEnrollment;

class StoreClassEnrollmentRequest extends FormRequest
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
            'assignment_type' => ['nullable', 'string', Rule::in(ClassEnrollment::ASSIGNMENT_TYPES)],
            'enrolled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'classe_id.required' => 'La classe est obligatoire.',
            'classe_id.exists' => 'La classe est introuvable.',
            'assignment_type.in' => "Le type d'inscription est invalide.",
            'enrolled_at.date' => "La date d'inscription est invalide.",
            'notes.max' => 'Les notes ne peuvent pas dépasser 1000 caractères.',
        ];
    }
}
