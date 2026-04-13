<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AutoAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', Rule::exists('tenant.students', 'id')],
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => ['required', 'integer', Rule::exists('tenant.groups', 'id')],
            'method' => ['nullable', Rule::in(['balanced', 'alphabetic', 'random', 'option'])],
        ];
    }

    public function messages(): array
    {
        return [
            'student_ids.required' => 'La liste des étudiants est obligatoire',
            'student_ids.min' => 'Au moins un étudiant est requis',
            'group_ids.required' => 'La liste des groupes est obligatoire',
            'group_ids.min' => 'Au moins un groupe est requis',
            'method.in' => 'La méthode doit être balanced, alphabetic, random ou option',
        ];
    }

    public function getAssignmentMethod(): string
    {
        return $this->input('method', 'balanced');
    }
}
