<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidateJustificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['approved', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'La décision est obligatoire.',
            'decision.in' => 'La décision doit être « approved » ou « rejected ».',
        ];
    }
}
