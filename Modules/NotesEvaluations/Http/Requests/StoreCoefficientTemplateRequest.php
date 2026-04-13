<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCoefficientTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'evaluations' => ['required', 'array', 'min:1'],
            'evaluations.*.type' => ['required', 'string', 'max:50'],
            'evaluations.*.coefficient' => ['required', 'numeric', 'min:0.25', 'max:10'],
            'evaluations.*.max_score' => ['nullable', 'numeric', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du template est obligatoire.',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'evaluations.required' => 'Les évaluations sont obligatoires.',
            'evaluations.min' => 'Il faut au moins une évaluation.',
            'evaluations.*.type.required' => 'Le type d\'évaluation est obligatoire.',
            'evaluations.*.coefficient.required' => 'Le coefficient est obligatoire.',
            'evaluations.*.coefficient.min' => 'Le coefficient doit être au moins de 0.25.',
            'evaluations.*.coefficient.max' => 'Le coefficient ne peut pas dépasser 10.',
        ];
    }
}
