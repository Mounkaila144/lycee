<?php

namespace Modules\NotesEvaluations\Http\Requests\Lycee;

use Illuminate\Foundation\Http\FormRequest;

class SaveClassEvaluationGradesRequest extends FormRequest
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
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.student_id' => ['required', 'integer', 'distinct'],
            'grades.*.score' => ['nullable', 'numeric', 'min:0'],
            'grades.*.is_absent' => ['sometimes', 'boolean'],
            'grades.*.comment' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grades.required' => 'La liste des notes est obligatoire.',
            'grades.array' => 'La liste des notes est invalide.',
            'grades.min' => 'La liste des notes est vide.',
            'grades.*.student_id.required' => "L'élève est obligatoire pour chaque note.",
            'grades.*.student_id.distinct' => 'Un élève apparaît plusieurs fois.',
            'grades.*.score.numeric' => 'La note doit être un nombre.',
            'grades.*.score.min' => 'La note ne peut pas être négative.',
            'grades.*.is_absent.boolean' => "L'indicateur d'absence est invalide.",
            'grades.*.comment.max' => 'Le commentaire ne doit pas dépasser 200 caractères.',
        ];
    }
}
