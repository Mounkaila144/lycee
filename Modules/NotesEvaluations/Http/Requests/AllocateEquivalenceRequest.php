<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AllocateEquivalenceRequest extends FormRequest
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
            'module_id' => ['required', 'exists:modules,id'],
            'credits' => ['required', 'integer', 'min:1', 'max:30'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'module_id.required' => 'Le module est obligatoire.',
            'module_id.exists' => 'Le module sélectionné n\'existe pas.',
            'credits.required' => 'Les crédits sont obligatoires.',
            'credits.integer' => 'Les crédits doivent être un nombre entier.',
            'credits.min' => 'Les crédits doivent être au moins de 1.',
            'credits.max' => 'Les crédits ne peuvent pas dépasser 30.',
            'note.max' => 'La note ne peut pas dépasser 1000 caractères.',
        ];
    }
}
