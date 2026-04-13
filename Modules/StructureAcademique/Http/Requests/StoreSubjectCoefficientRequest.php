<?php

namespace Modules\StructureAcademique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\StructureAcademique\Entities\Level;

class StoreSubjectCoefficientRequest extends FormRequest
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
        $level = Level::on('tenant')->find($this->level_id);
        $seriesRequired = $level && in_array($level->code, ['1ERE', 'TLE']);

        return [
            'subject_id' => ['required', 'integer', Rule::exists('tenant.subjects', 'id')],
            'level_id' => ['required', 'integer', Rule::exists('tenant.levels', 'id')],
            'series_id' => [$seriesRequired ? 'required' : 'nullable', 'integer', Rule::exists('tenant.series', 'id')],
            'coefficient' => ['required', 'numeric', 'min:1', 'max:8'],
            'hours_per_week' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    /**
     * Custom validation after rules pass
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $exists = \DB::connection('tenant')
                ->table('subject_class_coefficients')
                ->where('subject_id', $this->subject_id)
                ->where('level_id', $this->level_id)
                ->where('series_id', $this->series_id)
                ->exists();

            if ($exists) {
                $validator->errors()->add('subject_id', 'Cette matière est déjà configurée pour ce niveau/série.');
            }
        });
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            'subject_id.required' => 'La matière est obligatoire.',
            'level_id.required' => 'Le niveau est obligatoire.',
            'series_id.required' => 'La série est obligatoire pour les niveaux 1ère et Tle.',
            'coefficient.required' => 'Le coefficient est obligatoire.',
            'coefficient.min' => 'Le coefficient doit être au minimum 1.',
            'coefficient.max' => 'Le coefficient ne peut pas dépasser 8.',
        ];
    }
}
