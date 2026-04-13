<?php

namespace Modules\StructureAcademique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\StructureAcademique\Entities\Classe;

class UpdateClasseRequest extends FormRequest
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
            if ($this->head_teacher_id && $this->headTeacherAlreadyAssigned()) {
                $validator->errors()->add(
                    'head_teacher_id',
                    'Cet enseignant est déjà professeur principal d\'une autre classe pour cette année scolaire.'
                );
            }
        });
    }

    /**
     * Vérifie si l'enseignant est déjà PP d'une autre classe pour cette année
     */
    private function headTeacherAlreadyAssigned(): bool
    {
        $classeId = $this->route('classe');
        $classe = Classe::on('tenant')->find($classeId);

        if (! $classe) {
            return false;
        }

        return Classe::on('tenant')
            ->where('academic_year_id', $classe->academic_year_id)
            ->where('head_teacher_id', $this->head_teacher_id)
            ->where('id', '!=', $classe->id)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            'max_capacity.min' => 'La capacité doit être d\'au moins 1.',
            'max_capacity.max' => 'La capacité ne peut pas dépasser 200.',
            'head_teacher_id.exists' => 'L\'enseignant sélectionné n\'existe pas.',
        ];
    }
}
