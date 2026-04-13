<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class YearComparisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'year_1_id' => ['required', 'integer', 'exists:tenant.academic_years,id'],
            'year_2_id' => ['required', 'integer', 'exists:tenant.academic_years,id', 'different:year_1_id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'year_1_id.required' => 'La première année académique est obligatoire.',
            'year_1_id.exists' => 'La première année académique sélectionnée n\'existe pas.',
            'year_2_id.required' => 'La deuxième année académique est obligatoire.',
            'year_2_id.exists' => 'La deuxième année académique sélectionnée n\'existe pas.',
            'year_2_id.different' => 'Les deux années académiques doivent être différentes.',
        ];
    }
}
