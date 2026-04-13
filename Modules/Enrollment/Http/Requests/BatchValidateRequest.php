<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchValidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enrollment_ids' => ['required', 'array', 'min:1', 'max:100'],
            'enrollment_ids.*' => ['required', 'integer', 'exists:tenant.pedagogical_enrollments,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'enrollment_ids.required' => 'La liste des inscriptions est obligatoire.',
            'enrollment_ids.array' => 'La liste des inscriptions doit être un tableau.',
            'enrollment_ids.min' => 'Sélectionnez au moins une inscription.',
            'enrollment_ids.max' => 'Vous ne pouvez pas valider plus de 100 inscriptions à la fois.',
            'enrollment_ids.*.exists' => 'Une ou plusieurs inscriptions sélectionnées n\'existent pas.',
        ];
    }
}
