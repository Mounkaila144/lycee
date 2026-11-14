<?php

namespace Modules\Exams\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Exams\Entities\ExamSession;

/**
 * Création d'une session d'examen lycée : une matière, pour une classe
 * (composition) ou pour tout un niveau (examen blanc commun).
 * Tant que la migration lycée n'est pas jouée, l'ancien format (module_id) reste exigé.
 */
class StoreExamSessionRequest extends FormRequest
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
            'evaluation_period_id' => ['nullable', 'integer', Rule::exists('tenant.academic_periods', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(['normal', 'rattrapage', 'special'])],
            'exam_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i,H:i:s'],
            'end_time' => ['required', 'date_format:H:i,H:i:s', 'after:start_time'],
            'instructions' => ['nullable', 'string'],
            'allowed_materials' => ['nullable', 'array'],
            'allowed_materials.*' => ['string', 'max:255'],
            'room_ids' => ['nullable', 'array'],
            'room_ids.*' => ['integer', 'distinct', Rule::exists('tenant.rooms', 'id')->whereNull('deleted_at')],
        ];

        if (! ExamSession::hasLyceeColumns()) {
            return array_merge($rules, [
                'module_id' => ['required', 'integer', Rule::exists('tenant.modules', 'id')],
                'academic_year_id' => ['required', 'integer', Rule::exists('tenant.academic_years', 'id')],
            ]);
        }

        return array_merge($rules, [
            'subject_id' => ['required', 'integer', Rule::exists('tenant.subjects', 'id')->whereNull('deleted_at')],
            'classe_id' => ['nullable', 'required_without:level_id', 'integer', Rule::exists('tenant.classes', 'id')->whereNull('deleted_at')],
            'level_id' => ['nullable', 'required_without:classe_id', 'integer', Rule::exists('tenant.levels', 'id')],
            'academic_year_id' => ['nullable', 'required_without:classe_id', 'integer', Rule::exists('tenant.academic_years', 'id')],
            'semester_id' => ['nullable', 'integer', Rule::exists('tenant.semesters', 'id')->whereNull('deleted_at')],
            'module_id' => ['nullable', 'integer', Rule::exists('tenant.modules', 'id')],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_id.required' => 'La matière est obligatoire.',
            'subject_id.exists' => 'La matière sélectionnée est introuvable.',
            'classe_id.required_without' => 'Choisissez une classe ou un niveau.',
            'classe_id.exists' => 'La classe sélectionnée est introuvable.',
            'level_id.required_without' => 'Choisissez une classe ou un niveau.',
            'level_id.exists' => 'Le niveau sélectionné est introuvable.',
            'academic_year_id.required_without' => "L'année scolaire est obligatoire pour un examen de niveau.",
            'academic_year_id.required' => "L'année scolaire est obligatoire.",
            'module_id.required' => 'Le module est obligatoire.',
            'title.required' => "L'intitulé de l'épreuve est obligatoire.",
            'type.required' => "Le type d'épreuve est obligatoire.",
            'type.in' => "Le type d'épreuve est invalide.",
            'exam_date.required' => "La date de l'épreuve est obligatoire.",
            'exam_date.date' => "La date de l'épreuve est invalide.",
            'start_time.required' => "L'heure de début est obligatoire.",
            'start_time.date_format' => "L'heure de début doit être au format HH:MM.",
            'end_time.required' => "L'heure de fin est obligatoire.",
            'end_time.date_format' => "L'heure de fin doit être au format HH:MM.",
            'end_time.after' => "L'heure de fin doit être postérieure à l'heure de début.",
            'room_ids.*.exists' => 'Une des salles sélectionnées est introuvable.',
        ];
    }
}
