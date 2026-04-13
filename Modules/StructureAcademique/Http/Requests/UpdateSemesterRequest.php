<?php

namespace Modules\StructureAcademique\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Modules\StructureAcademique\Entities\Semester;

class UpdateSemesterRequest extends FormRequest
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
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            'start_date.required' => 'La date de début est obligatoire.',
            'end_date.required' => 'La date de fin est obligatoire.',
            'end_date.after' => 'La date de fin doit être postérieure à la date de début.',
        ];
    }

    /**
     * Validation de cohérence inter-semestres
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $semesterId = $this->route('semester');
            $semester = Semester::on('tenant')->with('academicYear')->find($semesterId);

            if (! $semester) {
                return;
            }

            $academicYear = $semester->academicYear;
            $startDate = Carbon::parse($this->start_date);
            $endDate = Carbon::parse($this->end_date);

            // Vérifier que les dates restent dans les bornes de l'année scolaire
            if ($startDate->lt($academicYear->start_date)) {
                $validator->errors()->add('start_date', 'La date de début ne peut pas être avant le début de l\'année scolaire.');
            }

            if ($endDate->gt($academicYear->end_date)) {
                $validator->errors()->add('end_date', 'La date de fin ne peut pas être après la fin de l\'année scolaire.');
            }

            // Vérifier la cohérence avec l'autre semestre
            $otherSemester = Semester::on('tenant')
                ->where('academic_year_id', $semester->academic_year_id)
                ->where('id', '!=', $semester->id)
                ->first();

            if ($otherSemester) {
                if ($semester->name === 'S1' && $endDate->gte($otherSemester->start_date)) {
                    $validator->errors()->add('end_date', 'La fin du S1 doit être avant le début du S2.');
                }

                if ($semester->name === 'S2' && $startDate->lte($otherSemester->end_date)) {
                    $validator->errors()->add('start_date', 'Le début du S2 doit être après la fin du S1.');
                }
            }
        });
    }
}
