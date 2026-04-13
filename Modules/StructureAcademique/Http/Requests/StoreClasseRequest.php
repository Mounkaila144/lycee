<?php

namespace Modules\StructureAcademique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Level;

class StoreClasseRequest extends FormRequest
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
        return [
            'academic_year_id' => ['required', Rule::exists('tenant.academic_years', 'id')],
            'level_id' => ['required', Rule::exists('tenant.levels', 'id')],
            'series_id' => ['nullable', Rule::exists('tenant.series', 'id')],
            'section' => ['nullable', 'string', 'max:10'],
            'max_capacity' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'classroom' => ['nullable', 'string', 'max:255'],
            'head_teacher_id' => ['nullable', Rule::exists('tenant.users', 'id')],
        ];
    }

    /**
     * Validation supplémentaire
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->level_id && $this->requiresSeriesButMissing()) {
                $validator->errors()->add(
                    'series_id',
                    'La série est obligatoire pour les niveaux 1ère et Terminale.'
                );
            }

            if ($this->head_teacher_id && $this->academic_year_id && $this->headTeacherAlreadyAssigned()) {
                $validator->errors()->add(
                    'head_teacher_id',
                    'Cet enseignant est déjà professeur principal d\'une autre classe pour cette année scolaire.'
                );
            }
        });
    }

    /**
     * Vérifie si le niveau choisi nécessite une série
     */
    private function requiresSeriesButMissing(): bool
    {
        if ($this->series_id) {
            return false;
        }

        $level = Level::on('tenant')->find($this->level_id);
        if (! $level) {
            return false;
        }

        return in_array($level->code, ['1ERE', 'TLE']);
    }

    /**
     * Vérifie si l'enseignant est déjà PP d'une autre classe pour cette année
     */
    private function headTeacherAlreadyAssigned(): bool
    {
        return Classe::on('tenant')
            ->where('academic_year_id', $this->academic_year_id)
            ->where('head_teacher_id', $this->head_teacher_id)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            'academic_year_id.required' => 'L\'année scolaire est obligatoire.',
            'academic_year_id.exists' => 'L\'année scolaire sélectionnée n\'existe pas.',
            'level_id.required' => 'Le niveau est obligatoire.',
            'level_id.exists' => 'Le niveau sélectionné n\'existe pas.',
            'series_id.exists' => 'La série sélectionnée n\'existe pas.',
            'max_capacity.min' => 'La capacité doit être d\'au moins 1.',
            'max_capacity.max' => 'La capacité ne peut pas dépasser 200.',
            'head_teacher_id.exists' => 'L\'enseignant sélectionné n\'existe pas.',
        ];
    }
}
