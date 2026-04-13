<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grades' => ['required', 'array', 'min:1', 'max:500'],
            'grades.*.matricule' => ['required', 'string'],
            'grades.*.score' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'grades.*.is_absent' => ['boolean'],
            'grades.*.comment' => ['nullable', 'string', 'max:200'],
            'overwrite_existing' => ['boolean'],
            'force' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'grades.required' => 'Les notes sont obligatoires.',
            'grades.min' => 'Au moins une note est requise.',
            'grades.max' => 'Maximum 500 notes par requête.',
            'grades.*.matricule.required' => 'Le matricule est obligatoire.',
            'grades.*.score.min' => 'La note doit être supérieure ou égale à 0.',
            'grades.*.score.max' => 'La note doit être inférieure ou égale à 20.',
            'grades.*.comment.max' => 'Le commentaire ne peut pas dépasser 200 caractères.',
        ];
    }
}
