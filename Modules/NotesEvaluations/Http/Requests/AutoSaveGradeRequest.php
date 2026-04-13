<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AutoSaveGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'evaluation_id' => ['required', 'integer', 'exists:module_evaluation_configs,id'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'is_absent' => ['boolean'],
            'comment' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => "L'identifiant de l'étudiant est obligatoire.",
            'evaluation_id.required' => "L'identifiant de l'évaluation est obligatoire.",
            'score.min' => 'La note doit être supérieure ou égale à 0.',
            'score.max' => 'La note doit être inférieure ou égale à 20.',
            'comment.max' => 'Le commentaire ne peut pas dépasser 200 caractères.',
        ];
    }
}
