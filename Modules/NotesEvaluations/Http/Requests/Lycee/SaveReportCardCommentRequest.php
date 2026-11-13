<?php

namespace Modules\NotesEvaluations\Http\Requests\Lycee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveReportCardCommentRequest extends FormRequest
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
            'student_id' => ['required', 'integer', Rule::exists('tenant.students', 'id')],
            'semester_id' => ['required', 'integer', Rule::exists('tenant.semesters', 'id')],
            'subject_id' => ['nullable', 'integer', Rule::exists('tenant.subjects', 'id')],
            'comment' => ['present', 'nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.required' => "L'élève est obligatoire.",
            'student_id.exists' => "L'élève sélectionné est introuvable.",
            'semester_id.required' => 'Le semestre est obligatoire.',
            'semester_id.exists' => 'Le semestre sélectionné est introuvable.',
            'subject_id.exists' => 'La matière sélectionnée est introuvable.',
            'comment.present' => "L'appréciation est obligatoire (vide pour la supprimer).",
            'comment.max' => "L'appréciation ne doit pas dépasser 300 caractères.",
        ];
    }
}
