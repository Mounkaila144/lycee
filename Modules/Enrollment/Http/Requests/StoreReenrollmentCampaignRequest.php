<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReenrollmentCampaignRequest extends FormRequest
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
            'from_academic_year_id' => ['required', 'exists:academic_years,id'],
            'to_academic_year_id' => ['required', 'exists:academic_years,id', 'different:from_academic_year_id'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'eligible_programs' => ['nullable', 'array'],
            'eligible_programs.*' => ['integer', 'exists:programmes,id'],
            'eligible_levels' => ['nullable', 'array'],
            'eligible_levels.*' => ['string', 'in:L1,L2,L3,M1,M2'],
            'required_documents' => ['nullable', 'array'],
            'required_documents.*' => ['string'],
            'fees_config' => ['nullable', 'array'],
            'fees_config.*' => ['numeric', 'min:0'],
            'min_ects_required' => ['required', 'integer', 'min:0', 'max:60'],
            'check_financial_clearance' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'from_academic_year_id.required' => 'L\'année académique de départ est obligatoire.',
            'to_academic_year_id.required' => 'L\'année académique cible est obligatoire.',
            'to_academic_year_id.different' => 'L\'année cible doit être différente de l\'année de départ.',
            'name.required' => 'Le nom de la campagne est obligatoire.',
            'start_date.required' => 'La date de début est obligatoire.',
            'start_date.after_or_equal' => 'La date de début doit être aujourd\'hui ou plus tard.',
            'end_date.required' => 'La date de fin est obligatoire.',
            'end_date.after' => 'La date de fin doit être après la date de début.',
            'min_ects_required.required' => 'Le nombre minimum d\'ECTS est obligatoire.',
        ];
    }
}
