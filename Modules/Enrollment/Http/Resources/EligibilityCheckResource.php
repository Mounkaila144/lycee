<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EligibilityCheckResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'is_eligible' => $this['is_eligible'],
            'checks' => [
                'is_active' => [
                    'passed' => $this['is_active'],
                    'label' => 'Étudiant actif',
                ],
                'has_previous_enrollment' => [
                    'passed' => $this['has_previous_enrollment'],
                    'label' => 'Inscription précédente',
                ],
                'has_min_ects' => [
                    'passed' => $this['has_min_ects'],
                    'label' => 'ECTS minimum validés',
                    'details' => sprintf(
                        '%d/%d ECTS',
                        $this['validated_ects'] ?? 0,
                        $this['required_ects'] ?? 0
                    ),
                ],
                'financial_clearance' => [
                    'passed' => $this['financial_clearance'],
                    'label' => 'Apurement financier',
                ],
                'no_disciplinary_exclusion' => [
                    'passed' => $this['no_disciplinary_exclusion'],
                    'label' => 'Pas d\'exclusion disciplinaire',
                ],
                'program_eligible' => [
                    'passed' => $this['program_eligible'] ?? true,
                    'label' => 'Programme éligible',
                ],
                'level_eligible' => [
                    'passed' => $this['level_eligible'] ?? true,
                    'label' => 'Niveau éligible',
                ],
            ],
            'validated_ects' => $this['validated_ects'] ?? 0,
            'required_ects' => $this['required_ects'] ?? 0,
        ];
    }
}
