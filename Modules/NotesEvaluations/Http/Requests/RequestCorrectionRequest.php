<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proposed_value' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'proposed_is_absent' => ['boolean'],
            'reason' => ['required', 'string', 'min:20', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'proposed_value.min' => 'La note proposée doit être supérieure ou égale à 0.',
            'proposed_value.max' => 'La note proposée doit être inférieure ou égale à 20.',
            'reason.required' => 'Le motif de correction est obligatoire.',
            'reason.min' => 'Le motif doit contenir au moins 20 caractères.',
            'reason.max' => 'Le motif ne peut pas dépasser 500 caractères.',
        ];
    }
}
