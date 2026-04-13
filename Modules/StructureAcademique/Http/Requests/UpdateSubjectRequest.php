<?php

namespace Modules\StructureAcademique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\StructureAcademique\Enums\SubjectCategory;

class UpdateSubjectRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Préparer les données avant validation
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper($this->code)]);
        }
    }

    /**
     * Règles de validation
     */
    public function rules(): array
    {
        $subjectId = $this->route('subject');

        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('tenant.subjects', 'code')->ignore($subjectId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'short_name' => ['sometimes', 'string', 'max:100'],
            'category' => ['sometimes', Rule::enum(SubjectCategory::class)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Ce code matière existe déjà.',
            'code.max' => 'Le code ne peut pas dépasser 50 caractères.',
        ];
    }
}
