<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCreditsRequest extends FormRequest
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
            'credits_ects' => ['required', 'integer', 'min:1', 'max:30'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'credits_ects.required' => 'Les crédits ECTS sont obligatoires.',
            'credits_ects.integer' => 'Les crédits ECTS doivent être un nombre entier.',
            'credits_ects.min' => 'Les crédits ECTS doivent être au moins de 1.',
            'credits_ects.max' => 'Les crédits ECTS ne peuvent pas dépasser 30.',
            'reason.max' => 'La raison ne peut pas dépasser 500 caractères.',
        ];
    }
}
