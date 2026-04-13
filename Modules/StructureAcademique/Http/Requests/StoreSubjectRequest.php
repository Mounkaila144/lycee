<?php

namespace Modules\StructureAcademique\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\StructureAcademique\Enums\SubjectCategory;

class StoreSubjectRequest extends FormRequest
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
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('tenant.subjects', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::enum(SubjectCategory::class)],
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
            'code.required' => 'Le code matière est obligatoire.',
            'code.unique' => 'Ce code matière existe déjà.',
            'code.max' => 'Le code ne peut pas dépasser 50 caractères.',
            'name.required' => 'Le nom est obligatoire.',
            'short_name.required' => 'Le nom abrégé est obligatoire.',
            'category.required' => 'La catégorie est obligatoire.',
        ];
    }
}
