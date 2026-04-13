<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordDecisionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:tenant.students,id'],
            'decision' => ['required', 'string', 'in:validated,compensated,retake,repeat_year,exclusion,conditional,deferred'],
            'justification' => ['nullable', 'string'],
            'conditions' => ['nullable', 'array'],
            'is_exceptional' => ['sometimes', 'boolean'],
            'exceptional_reason' => ['required_if:is_exceptional,true', 'nullable', 'string'],
            'votes_for' => ['nullable', 'integer', 'min:0'],
            'votes_against' => ['nullable', 'integer', 'min:0'],
            'abstentions' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'L\'étudiant est requis.',
            'student_id.exists' => 'L\'étudiant sélectionné n\'existe pas.',
            'decision.required' => 'La décision est requise.',
            'decision.in' => 'La décision doit être: validated, compensated, retake, repeat_year, exclusion, conditional ou deferred.',
            'exceptional_reason.required_if' => 'La raison est requise pour une décision exceptionnelle.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'student_id' => 'étudiant',
            'decision' => 'décision',
            'justification' => 'justification',
            'conditions' => 'conditions',
            'is_exceptional' => 'décision exceptionnelle',
            'exceptional_reason' => 'raison exceptionnelle',
            'votes_for' => 'votes pour',
            'votes_against' => 'votes contre',
            'abstentions' => 'abstentions',
        ];
    }
}
