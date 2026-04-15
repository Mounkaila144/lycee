<?php

namespace Modules\StructureAcademique\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationPeriodRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:Jour férié,Vacances,Inscription pédagogique,Session examens,Rattrapage,Autre'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la période est obligatoire.',
            'type.required' => 'Le type de période est obligatoire.',
            'type.in' => 'Le type de période sélectionné est invalide.',
            'start_date.required' => 'La date de début est obligatoire.',
            'end_date.required' => 'La date de fin est obligatoire.',
            'end_date.after' => 'La date de fin doit être après la date de début.',
        ];
    }

    /**
     * Validation supplémentaire
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $semesterId = $this->route('semester');

            if ($semesterId && $this->start_date && $this->end_date) {
                // Charger le semestre manuellement
                $semester = \Modules\StructureAcademique\Entities\Semester::on('tenant')
                    ->find($semesterId);

                if (! $semester) {
                    $validator->errors()->add('semester', 'Semestre introuvable.');

                    return;
                }

                $startDate = Carbon::parse($this->start_date);
                $endDate = Carbon::parse($this->end_date);

                // Vérifier que la période est dans les limites du semestre
                if ($startDate->lt($semester->start_date) || $endDate->gt($semester->end_date)) {
                    $validator->errors()->add(
                        'start_date',
                        'Les dates de la période doivent être dans les limites du semestre ('
                        .$semester->start_date->format('d/m/Y').' - '
                        .$semester->end_date->format('d/m/Y').').'
                    );
                }

                // Vérifier les chevauchements avec d'autres périodes
                $overlapping = $semester->academicPeriods()
                    ->where(function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('start_date', [$startDate, $endDate])
                            ->orWhereBetween('end_date', [$startDate, $endDate])
                            ->orWhere(function ($q) use ($startDate, $endDate) {
                                $q->where('start_date', '<=', $startDate)
                                    ->where('end_date', '>=', $endDate);
                            });
                    })
                    ->exists();

                if ($overlapping) {
                    $validator->errors()->add(
                        'start_date',
                        'Cette période chevauche une autre période existante.'
                    );
                }
            }
        });
    }
}
