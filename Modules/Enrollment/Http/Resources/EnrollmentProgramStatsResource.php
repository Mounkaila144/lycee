<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentProgramStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'program' => $this->resource['program'] ?? null,
            'enrollments' => [
                'total' => $this->resource['total'] ?? 0,
                'male' => $this->resource['male'] ?? 0,
                'female' => $this->resource['female'] ?? 0,
            ],
            'demographics' => [
                'gender_ratio' => $this->resource['gender_ratio'] ?? 0,
                'average_age' => $this->resource['average_age'] ?? null,
            ],
            'by_level' => $this->resource['by_level'] ?? [],
            'comparison' => [
                'previous_year_count' => $this->resource['previous_year_count'] ?? 0,
                'growth_rate' => $this->resource['growth_rate'] ?? 0,
            ],
        ];
    }
}
