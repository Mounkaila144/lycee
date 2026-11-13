<?php

namespace Modules\NotesEvaluations\Http\Requests\Lycee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\NotesEvaluations\Entities\ClassEvaluation;

class ListClassEvaluationsRequest extends FormRequest
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
            'classe_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'semester_id' => ['nullable', 'integer'],
            'teacher_id' => ['nullable', 'integer'],
            'academic_year_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::in(ClassEvaluation::STATUSES)],
            'type' => ['nullable', 'string', Rule::in(ClassEvaluation::TYPES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Le statut doit être : draft, submitted ou published.',
            'type.in' => 'Le type doit être : devoir, interrogation ou composition.',
            'per_page.max' => 'Au plus 200 évaluations par page.',
        ];
    }
}
