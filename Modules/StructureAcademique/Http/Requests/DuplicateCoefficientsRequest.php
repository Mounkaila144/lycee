<?php

namespace Modules\StructureAcademique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\StructureAcademique\Entities\Level;

class DuplicateCoefficientsRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation
     */
    public function rules(): array
    {
        $targetLevel = Level::on('tenant')->find($this->target_level_id);
        $targetSeriesRequired = $targetLevel && in_array($targetLevel->code, ['1ERE', 'TLE']);

        return [
            'source_level_id' => ['required', 'integer', Rule::exists('tenant.levels', 'id')],
            'source_series_id' => ['nullable', 'integer', Rule::exists('tenant.series', 'id')],
            'target_level_id' => ['required', 'integer', Rule::exists('tenant.levels', 'id')],
            'target_series_id' => [$targetSeriesRequired ? 'required' : 'nullable', 'integer', Rule::exists('tenant.series', 'id')],
            'strategy' => ['required', Rule::in(['replace', 'merge'])],
        ];
    }

    /**
     * Custom validation: source != target
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (
                $this->source_level_id == $this->target_level_id
                && $this->source_series_id == $this->target_series_id
            ) {
                $validator->errors()->add('target_level_id', 'La source et la cible doivent être différentes.');
            }
        });
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            'source_level_id.required' => 'Le niveau source est obligatoire.',
            'target_level_id.required' => 'Le niveau cible est obligatoire.',
            'target_series_id.required' => 'La série cible est obligatoire pour les niveaux 1ère et Tle.',
            'strategy.required' => 'La stratégie de duplication est obligatoire.',
            'strategy.in' => 'La stratégie doit être "replace" ou "merge".',
        ];
    }
}
