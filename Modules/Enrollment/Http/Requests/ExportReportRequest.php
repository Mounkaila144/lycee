<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportReportRequest extends FormRequest
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
            'academic_year_id' => ['nullable', 'integer', 'exists:tenant.academic_years,id'],
            'program_id' => ['nullable', 'integer', 'exists:tenant.programmes,id'],
            'level' => ['nullable', 'string', 'in:L1,L2,L3,M1,M2'],
            'status' => ['nullable', 'string'],
            'format' => ['nullable', 'string', 'in:pdf,excel,csv'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'academic_year_id.exists' => 'L\'année académique sélectionnée n\'existe pas.',
            'program_id.exists' => 'Le programme sélectionné n\'existe pas.',
            'level.in' => 'Le niveau doit être L1, L2, L3, M1 ou M2.',
            'format.in' => 'Le format doit être pdf, excel ou csv.',
        ];
    }
}
