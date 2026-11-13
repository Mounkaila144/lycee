<?php

namespace Modules\NotesEvaluations\Http\Requests\Lycee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\NotesEvaluations\Entities\ClassEvaluation;

class StoreClassEvaluationRequest extends FormRequest
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
            'classe_id' => ['required', 'integer', Rule::exists('tenant.classes', 'id')->whereNull('deleted_at')],
            'subject_id' => ['required', 'integer', Rule::exists('tenant.subjects', 'id')->whereNull('deleted_at')],
            'semester_id' => ['required', 'integer', Rule::exists('tenant.semesters', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', Rule::in(ClassEvaluation::TYPES)],
            'evaluation_date' => ['required', 'date'],
            'max_score' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'weight' => ['nullable', 'numeric', 'min:0.25', 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'classe_id.required' => 'La classe est obligatoire.',
            'classe_id.exists' => 'La classe sélectionnée est introuvable.',
            'subject_id.required' => 'La matière est obligatoire.',
            'subject_id.exists' => 'La matière sélectionnée est introuvable.',
            'semester_id.required' => 'Le semestre est obligatoire.',
            'semester_id.exists' => 'Le semestre sélectionné est introuvable.',
            'title.required' => "L'intitulé de l'évaluation est obligatoire.",
            'title.max' => "L'intitulé ne doit pas dépasser 150 caractères.",
            'type.required' => "Le type d'évaluation est obligatoire.",
            'type.in' => 'Le type doit être : devoir, interrogation ou composition.',
            'evaluation_date.required' => "La date de l'évaluation est obligatoire.",
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
