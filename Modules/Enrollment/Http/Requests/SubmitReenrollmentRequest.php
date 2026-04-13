<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitReenrollmentRequest extends FormRequest
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
            'has_accepted_rules' => ['required', 'accepted'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'has_accepted_rules.required' => 'Vous devez accepter le règlement intérieur.',
            'has_accepted_rules.accepted' => 'Vous devez accepter le règlement intérieur.',
        ];
    }
}
