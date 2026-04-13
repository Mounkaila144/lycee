<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOptionRequest extends FormRequest
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
        $optionId = $this->route('option')?->id ?? $this->route('option');

        return [
            'programme_id' => ['sometimes', 'integer', 'exists:tenant.programmes,id'],
            'level' => ['sometimes', 'string', 'max:10', 'in:L1,L2,L3,M1,M2'],
            'code' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('tenant.options', 'code')->ignore($optionId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'prerequisites' => ['nullable', 'array'],
            'prerequisites.*' => ['numeric', 'min:0', 'max:20'],
            'is_mandatory' => ['boolean'],
            'choice_start_date' => ['sometimes', 'date'],
            'choice_end_date' => ['sometimes', 'date', 'after:choice_start_date'],
            'status' => ['sometimes', 'in:Open,Closed,Archived'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'programme_id.exists' => 'Le programme sélectionné n\'existe pas.',
            'level.in' => 'Le niveau doit être L1, L2, L3, M1 ou M2.',
            'code.unique' => 'Ce code d\'option existe déjà.',
            'capacity.min' => 'La capacité doit être d\'au moins 1 place.',
            'choice_end_date.after' => 'La date de fin doit être après la date de début.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('is_mandatory') && ! is_bool($this->is_mandatory)) {
            $this->merge([
                'is_mandatory' => filter_var($this->is_mandatory, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
