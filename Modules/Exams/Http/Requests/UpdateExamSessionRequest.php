<?php

namespace Modules\Exams\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Exams\Entities\ExamSession;

class UpdateExamSessionRequest extends FormRequest
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
        $rules = [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', Rule::in(['normal', 'rattrapage', 'special'])],
            'exam_date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'date_format:H:i,H:i:s'],
            'end_time' => ['sometimes', 'date_format:H:i,H:i:s'],
            'instructions' => ['nullable', 'string'],
            'allowed_materials' => ['nullable', 'array'],
        ];

        if (! ExamSession::hasLyceeColumns()) {
            return $rules;
        }

        return array_merge($rules, [
            'subject_id' => ['sometimes', 'integer', Rule::exists('tenant.subjects', 'id')->whereNull('deleted_at')],
            'classe_id' => ['nullable', 'integer', Rule::exists('tenant.classes', 'id')->whereNull('deleted_at')],
            'level_id' => ['nullable', 'integer', Rule::exists('tenant.levels', 'id')],
            'semester_id' => ['nullable', 'integer', Rule::exists('tenant.semesters', 'id')->whereNull('deleted_at')],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_id.exists' => 'La matière sélectionnée est introuvable.',
            'classe_id.exists' => 'La classe sélectionnée est introuvable.',
            'level_id.exists' => 'Le niveau sélectionné est introuvable.',
            'start_time.date_format' => "L'heure de début doit être au format HH:MM.",
            'end_time.date_format' => "L'heure de fin doit être au format HH:MM.",
        ];
    }
}
