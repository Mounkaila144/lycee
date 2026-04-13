<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchGenerateCardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_ids' => ['required', 'array', 'min:1', 'max:500'],
            'student_ids.*' => ['required', 'integer', 'exists:tenant.students,id'],
            'academic_year_id' => ['required', 'exists:tenant.academic_years,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_ids.required' => 'La liste des étudiants est obligatoire.',
            'student_ids.array' => 'La liste des étudiants doit être un tableau.',
            'student_ids.min' => 'Sélectionnez au moins un étudiant.',
            'student_ids.max' => 'Vous ne pouvez pas générer plus de 500 cartes à la fois.',
            'student_ids.*.exists' => 'Un ou plusieurs étudiants sélectionnés n\'existent pas.',
            'academic_year_id.required' => 'L\'année académique est obligatoire.',
            'academic_year_id.exists' => 'L\'année académique sélectionnée n\'existe pas.',
        ];
    }
}
