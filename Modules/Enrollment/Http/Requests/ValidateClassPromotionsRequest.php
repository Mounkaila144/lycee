<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Enrollment\Services\ClassPromotionService;

class ValidateClassPromotionsRequest extends FormRequest
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
            'source_classe_id' => ['required', 'integer', Rule::exists('tenant.classes', 'id')->whereNull('deleted_at')],
            'target_academic_year_id' => ['required', 'integer', Rule::exists('tenant.academic_years', 'id')->whereNull('deleted_at')],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.student_id' => ['required', 'integer', 'distinct'],
            'items.*.decision' => ['required', 'string', Rule::in(ClassPromotionService::DECISIONS)],
            'items.*.target_classe_id' => ['nullable', 'integer', 'required_unless:items.*.decision,'.ClassPromotionService::DECISION_DEPART],
            'items.*.depart_reason' => ['nullable', 'string', Rule::in(ClassPromotionService::DEPART_REASONS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_classe_id.required' => 'La classe source est obligatoire.',
            'source_classe_id.exists' => 'La classe source est introuvable.',
            'target_academic_year_id.required' => "L'année scolaire cible est obligatoire.",
            'target_academic_year_id.exists' => "L'année scolaire cible est introuvable.",
            'items.required' => 'La liste des décisions est obligatoire.',
            'items.min' => 'La liste des décisions est vide.',
            'items.max' => 'Trop de décisions envoyées en une fois.',
            'items.*.student_id.required' => "L'élève est obligatoire pour chaque ligne.",
            'items.*.student_id.distinct' => 'Un élève apparaît plusieurs fois.',
            'items.*.decision.required' => 'La décision est obligatoire pour chaque élève.',
            'items.*.decision.in' => 'La décision doit être passage, redoublement ou depart.',
            'items.*.target_classe_id.required_unless' => 'La classe cible est obligatoire pour un passage ou un redoublement.',
            'items.*.depart_reason.in' => 'Le motif de départ doit être Abandon, Transfert, Exclusion ou Diplôme.',
        ];
    }
}
