<?php

namespace Modules\NotesEvaluations\Http\Requests\Lycee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\NotesEvaluations\Entities\ClassEvaluation;

class UpdateClassEvaluationRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'type' => ['sometimes', 'required', 'string', Rule::in(ClassEvaluation::TYPES)],
            'evaluation_date' => ['sometimes', 'required', 'date'],
            'max_score' => ['sometimes', 'required', 'numeric', 'min:1', 'max:100'],
            'weight' => ['sometimes', 'required', 'numeric', 'min:0.25', 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => "L'intitulé de l'évaluation est obligatoire.",
            'title.max' => "L'intitulé ne doit pas dépasser 150 caractères.",
            'type.in' => 'Le type doit être : devoir, interrogation ou composition.',
            'evaluation_date.date' => "La date de l'évaluation est invalide.",
            'max_score.numeric' => 'Le barème doit être un nombre.',
            'max_score.min' => 'Le barème doit être au moins 1.',
            'max_score.max' => 'Le barème ne peut pas dépasser 100.',
            'weight.numeric' => 'Le poids doit être un nombre.',
            'weight.min' => 'Le poids doit être au moins 0,25.',
            'weight.max' => 'Le poids ne peut pas dépasser 10.',
        ];
    }
}
