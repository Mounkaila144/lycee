<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignOptionsRequest extends FormRequest
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
            'academic_year_id' => ['required', 'integer', 'exists:tenant.academic_years,id'],
            'programme_id' => ['required', 'integer', 'exists:tenant.programmes,id'],
            'level' => ['required', 'string', 'in:L1,L2,L3,M1,M2'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'academic_year_id.required' => 'L\'année académique est obligatoire.',
            'academic_year_id.exists' => 'L\'année académique sélectionnée n\'existe pas.',
            'programme_id.required' => 'Le programme est obligatoire.',
            'programme_id.exists' => 'Le programme sélectionné n\'existe pas.',
            'level.required' => 'Le niveau est obligatoire.',
            'level.in' => 'Le niveau doit être L1, L2, L3, M1 ou M2.',
        ];
    }
}
