<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeEquivalencesRequest extends FormRequest
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
            'origin_modules' => ['required', 'array', 'min:1'],
            'origin_modules.*.code' => ['nullable', 'string', 'max:50'],
            'origin_modules.*.name' => ['required', 'string', 'max:255'],
            'origin_modules.*.ects' => ['nullable', 'integer', 'min:0', 'max:30'],
            'origin_modules.*.hours' => ['nullable', 'integer', 'min:0', 'max:500'],
            'origin_modules.*.grade' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'origin_modules.required' => 'La liste des modules d\'origine est obligatoire.',
            'origin_modules.min' => 'Au moins un module d\'origine est requis.',
            'origin_modules.*.name.required' => 'Le nom du module est obligatoire.',
        ];
    }
}
