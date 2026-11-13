<?php

namespace Modules\StructureAcademique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\StructureAcademique\Entities\Classe;
use Modules\UsersGuard\Entities\TenantUser;

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
            'level_id' => ['sometimes', 'integer', Rule::exists('tenant.levels', 'id')],
            'series_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tenant.series', 'id')],
            'academic_year_id' => ['sometimes', 'integer', Rule::exists('tenant.academic_years', 'id')],
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
            if (! $this->head_teacher_id || ! $this->headTeacherChanged()) {
                return;
            }

            if (! $this->headTeacherIsTeacher()) {
                $validator->errors()->add(
                    'head_teacher_id',
                    'Le professeur principal doit avoir le rôle Professeur.'
                );
            }

            if ($this->headTeacherAlreadyAssigned()) {
                $validator->errors()->add(
                    'head_teacher_id',
                    'Cet enseignant est déjà professeur principal d\'une autre classe pour cette année scolaire.'
                );
            }
        });
    }

    /**
     * Le PP n'est revalidé que s'il change : une mise à jour qui renvoie le PP
     * actuel (ex. modification de la capacité) ne doit pas être bloquée.
     */
    private function headTeacherChanged(): bool
    {
        $classe = Classe::on('tenant')->find($this->route('classe'));

        return ! $classe || (int) $classe->head_teacher_id !== (int) $this->head_teacher_id;
    }

    /**
     * Vérifie que l'utilisateur choisi comme PP a le rôle Professeur
     */
    private function headTeacherIsTeacher(): bool
    {
        return TenantUser::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'Professeur'))
            ->whereKey($this->head_teacher_id)
            ->exists();
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
