<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchValidateEquivalencesRequest extends FormRequest
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
            'equivalence_ids' => ['required', 'array', 'min:1'],
            'equivalence_ids.*' => ['integer', 'exists:equivalences,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'equivalence_ids.required' => 'La liste des équivalences est obligatoire.',
            'equivalence_ids.min' => 'Au moins une équivalence est requise.',
        ];
    }
}
