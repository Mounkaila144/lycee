<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'exists:tenant.academic_years,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'academic_year_id.required' => 'L\'année académique est obligatoire.',
            'academic_year_id.exists' => 'L\'année académique sélectionnée n\'existe pas.',
        ];
    }
}
