<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOptionRequest extends FormRequest
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
            'programme_id' => ['required', 'integer', 'exists:tenant.programmes,id'],
            'level' => ['required', 'string', 'max:10', 'in:L1,L2,L3,M1,M2'],
            'code' => ['required', 'string', 'max:20', 'unique:tenant.options,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'prerequisites' => ['nullable', 'array'],
            'prerequisites.*' => ['numeric', 'min:0', 'max:20'],
            'is_mandatory' => ['boolean'],
            'choice_start_date' => ['required', 'date', 'after_or_equal:today'],
            'choice_end_date' => ['required', 'date', 'after:choice_start_date'],
            'status' => ['in:Open,Closed,Archived'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'programme_id.required' => 'Le programme est obligatoire.',
            'programme_id.exists' => 'Le programme sélectionné n\'existe pas.',
            'level.required' => 'Le niveau est obligatoire.',
            'level.in' => 'Le niveau doit être L1, L2, L3, M1 ou M2.',
            'code.required' => 'Le code est obligatoire.',
            'code.unique' => 'Ce code d\'option existe déjà.',
            'name.required' => 'Le nom est obligatoire.',
            'capacity.required' => 'La capacité est obligatoire.',
            'capacity.min' => 'La capacité doit être d\'au moins 1 place.',
            'choice_start_date.required' => 'La date de début de choix est obligatoire.',
            'choice_start_date.after_or_equal' => 'La date de début doit être aujourd\'hui ou ultérieure.',
            'choice_end_date.required' => 'La date de fin de choix est obligatoire.',
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
