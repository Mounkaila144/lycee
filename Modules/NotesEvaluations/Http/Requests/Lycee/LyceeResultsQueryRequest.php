<?php

namespace Modules\NotesEvaluations\Http\Requests\Lycee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LyceeResultsQueryRequest extends FormRequest
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
            'semester_id' => ['nullable', 'integer', Rule::exists('tenant.semesters', 'id')],
            'include_unpublished' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'semester_id.integer' => 'Le semestre est invalide.',
            'semester_id.exists' => 'Le semestre sélectionné est introuvable.',
            'include_unpublished.boolean' => 'Le paramètre include_unpublished doit valoir 0 ou 1.',
        ];
    }

    public function semesterId(): ?int
    {
        return $this->filled('semester_id') ? $this->integer('semester_id') : null;
    }
}
