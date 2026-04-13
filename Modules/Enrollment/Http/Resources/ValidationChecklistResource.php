<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ValidationChecklistResource extends JsonResource
{
    /**
     * Transform the resource into an array
     *
     * @param array{
     *     administrative: bool,
     *     modules_check: bool,
     *     ects_check: bool,
     *     total_ects: int,
     *     groups_check: bool,
     *     options_check: bool,
     *     prerequisites_check: bool,
     *     is_complete: bool
     * } $resource
     */
    public function toArray($request): array
    {
        return [
            'checks' => [
                [
                    'key' => 'administrative',
                    'label' => 'Inscription administrative validée',
                    'passed' => $this->resource['administrative'] ?? false,
                    'icon' => ($this->resource['administrative'] ?? false) ? '✅' : '❌',
                ],
                [
                    'key' => 'modules_check',
                    'label' => 'Modules obligatoires inscrits',
                    'passed' => $this->resource['modules_check'] ?? false,
                    'icon' => ($this->resource['modules_check'] ?? false) ? '✅' : '❌',
                ],
                [
                    'key' => 'ects_check',
                    'label' => 'Crédits ECTS conformes',
                    'passed' => $this->resource['ects_check'] ?? false,
                    'icon' => ($this->resource['ects_check'] ?? false) ? '✅' : '❌',
                    'details' => [
                        'total' => $this->resource['total_ects'] ?? 0,
                        'expected' => 30,
                    ],
                ],
                [
                    'key' => 'groups_check',
                    'label' => 'Affectation groupes TD/TP complète',
                    'passed' => $this->resource['groups_check'] ?? false,
                    'icon' => ($this->resource['groups_check'] ?? false) ? '✅' : '❌',
                ],
                [
                    'key' => 'options_check',
                    'label' => 'Options/spécialités choisies',
                    'passed' => $this->resource['options_check'] ?? false,
                    'icon' => ($this->resource['options_check'] ?? false) ? '✅' : '❌',
                ],
                [
                    'key' => 'prerequisites_check',
                    'label' => 'Prérequis des modules respectés',
                    'passed' => $this->resource['prerequisites_check'] ?? false,
                    'icon' => ($this->resource['prerequisites_check'] ?? false) ? '✅' : '❌',
                ],
            ],
            'is_complete' => $this->resource['is_complete'] ?? false,
            'can_validate' => $this->resource['is_complete'] ?? false,
            'missing_count' => collect($this->resource)
                ->filter(fn ($value, $key) => $value === false && ! in_array($key, ['is_complete', 'total_ects']))
                ->count(),
        ];
    }
}
