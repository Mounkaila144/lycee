<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReenrollmentCampaignRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'eligible_programs' => ['nullable', 'array'],
            'eligible_programs.*' => ['integer', 'exists:programmes,id'],
            'eligible_levels' => ['nullable', 'array'],
            'eligible_levels.*' => ['string', 'in:L1,L2,L3,M1,M2'],
            'required_documents' => ['nullable', 'array'],
            'required_documents.*' => ['string'],
            'fees_config' => ['nullable', 'array'],
            'fees_config.*' => ['numeric', 'min:0'],
            'min_ects_required' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'check_financial_clearance' => ['boolean'],
            'status' => ['sometimes', 'string', 'in:Draft,Active,Closed'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
