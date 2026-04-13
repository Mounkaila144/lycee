<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDeliberationSessionRequest extends FormRequest
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
            'semester_id' => ['required', 'integer', 'exists:tenant.semesters,id'],
            'programme_id' => ['nullable', 'integer', 'exists:tenant.programmes,id'],
            'session_type' => ['sometimes', 'string', 'in:regular,retake,exceptional'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'location' => ['nullable', 'string', 'max:255'],
            'agenda' => ['nullable', 'string'],
            'jury_members' => ['nullable', 'array'],
            'jury_members.*' => ['integer', 'exists:tenant.users,id'],
            'president_id' => ['nullable', 'integer', 'exists:tenant.users,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'semester_id.required' => 'Le semestre est requis.',
            'semester_id.exists' => 'Le semestre sélectionné n\'existe pas.',
            'programme_id.exists' => 'Le programme sélectionné n\'existe pas.',
            'session_type.in' => 'Le type de session doit être: regular, retake ou exceptional.',
            'scheduled_at.required' => 'La date de la session est requise.',
            'scheduled_at.after' => 'La date de la session doit être dans le futur.',
            'president_id.exists' => 'Le président sélectionné n\'existe pas.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'semester_id' => 'semestre',
            'programme_id' => 'programme',
            'session_type' => 'type de session',
            'scheduled_at' => 'date de session',
            'location' => 'lieu',
            'agenda' => 'ordre du jour',
            'jury_members' => 'membres du jury',
            'president_id' => 'président',
        ];
    }
}
