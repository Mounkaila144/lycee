<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRetakeGradesBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.retake_enrollment_id' => ['required', 'integer', 'exists:retake_enrollments,id'],
            'grades.*.score' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'grades.*.is_absent' => ['nullable', 'boolean'],
            'grades.*.comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'grades.required' => 'La liste des notes est requise.',
            'grades.array' => 'Les notes doivent être un tableau.',
            'grades.min' => 'Au moins une note doit être fournie.',
            'grades.*.retake_enrollment_id.required' => "L'inscription rattrapage est requise.",
            'grades.*.retake_enrollment_id.exists' => "Une inscription rattrapage n'existe pas.",
            'grades.*.score.numeric' => 'La note doit être un nombre.',
            'grades.*.score.min' => 'La note ne peut pas être négative.',
            'grades.*.score.max' => 'La note ne peut pas dépasser 20.',
        ];
    }
}
