<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCoefficientRequest extends FormRequest
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
            'coefficient' => ['required', 'numeric', 'min:0.25', 'max:10'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'coefficient.required' => 'Le coefficient est obligatoire.',
            'coefficient.numeric' => 'Le coefficient doit être un nombre.',
            'coefficient.min' => 'Le coefficient doit être au moins de 0.25.',
            'coefficient.max' => 'Le coefficient ne peut pas dépasser 10.',
            'reason.max' => 'La raison ne peut pas dépasser 500 caractères.',
        ];
    }
}
