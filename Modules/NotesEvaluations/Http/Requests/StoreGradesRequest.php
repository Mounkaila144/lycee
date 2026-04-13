<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grades' => ['required', 'array', 'min:1', 'max:500'],
            'grades.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'grades.*.evaluation_id' => ['required', 'integer', 'exists:module_evaluation_configs,id'],
            'grades.*.score' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'grades.*.is_absent' => ['boolean'],
            'grades.*.comment' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'grades.required' => 'Les notes sont obligatoires.',
            'grades.min' => 'Au moins une note est requise.',
            'grades.max' => 'Maximum 500 notes par requête.',
            'grades.*.score.min' => 'La note doit être supérieure ou égale à 0.',
            'grades.*.score.max' => 'La note doit être inférieure ou égale à 20.',
            'grades.*.comment.max' => 'Le commentaire ne peut pas dépasser 200 caractères.',
        ];
    }
}
