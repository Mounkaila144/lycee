<?php

namespace Modules\Exams\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEvaluationsFromSessionRequest extends FormRequest
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
            'semester_id' => ['nullable', 'integer', Rule::exists('tenant.semesters', 'id')->whereNull('deleted_at')],
            'title' => ['nullable', 'string', 'max:150'],
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
            'semester_id.exists' => 'Le semestre sélectionné est introuvable.',
            'title.max' => "L'intitulé ne doit pas dépasser 150 caractères.",
            'max_score.min' => 'Le barème doit être au moins 1.',
            'max_score.max' => 'Le barème ne peut pas dépasser 100.',
            'weight.min' => 'Le poids doit être au moins 0,25.',
            'weight.max' => 'Le poids ne peut pas dépasser 10.',
        ];
    }
}
