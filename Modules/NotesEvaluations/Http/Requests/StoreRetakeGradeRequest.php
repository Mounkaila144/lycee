<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRetakeGradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'retake_enrollment_id' => ['required', 'integer', 'exists:retake_enrollments,id'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'is_absent' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'retake_enrollment_id.required' => "L'inscription rattrapage est requise.",
            'retake_enrollment_id.exists' => "L'inscription rattrapage n'existe pas.",
            'score.numeric' => 'La note doit être un nombre.',
            'score.min' => 'La note ne peut pas être négative.',
            'score.max' => 'La note ne peut pas dépasser 20.',
            'comment.max' => 'Le commentaire ne peut pas dépasser 500 caractères.',
        ];
    }
}
