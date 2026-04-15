<?php

namespace Modules\StructureAcademique\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEvaluationPeriodRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:Jour férié,Vacances,Inscription pédagogique,Session examens,Rattrapage,Autre'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            'type.in' => 'Le type de période sélectionné est invalide.',
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
            $periodId = $this->route('period');

            if ($semesterId && $periodId) {
                // Charger les modèles manuellement
                $semester = \Modules\StructureAcademique\Entities\Semester::on('tenant')
                    ->find($semesterId);
                $period = \Modules\StructureAcademique\Entities\AcademicPeriod::on('tenant')
                    ->find($periodId);

                if (! $semester || ! $period) {
                    $validator->errors()->add('semester', 'Semestre ou période introuvable.');

                    return;
                }

                $startDate = $this->start_date ? Carbon::parse($this->start_date) : $period->start_date;
                $endDate = $this->end_date ? Carbon::parse($this->end_date) : $period->end_date;

                // Vérifier que la période est dans les limites du semestre
                if ($startDate->lt($semester->start_date) || $endDate->gt($semester->end_date)) {
                    $validator->errors()->add(
                        'start_date',
                        'Les dates de la période doivent être dans les limites du semestre.'
                    );
                }

                // Vérifier les chevauchements avec d'autres périodes (exclure la période actuelle)
                $overlapping = $semester->academicPeriods()
                    ->where('id', '!=', $period->id)
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
