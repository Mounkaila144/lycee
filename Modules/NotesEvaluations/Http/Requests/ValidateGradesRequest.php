<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'notes.max' => 'Les notes ne peuvent pas dépasser 1000 caractères.',
            'reason.max' => 'Le motif ne peut pas dépasser 1000 caractères.',
        ];
    }
}
