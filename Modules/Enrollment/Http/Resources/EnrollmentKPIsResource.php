<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentKPIsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'total_students' => $this->resource['total_students'] ?? 0,
            'active_students' => $this->resource['active_students'] ?? 0,
            'new_students' => $this->resource['new_students'] ?? 0,
            'reenrollments' => $this->resource['reenrollments'] ?? 0,
            'pedagogical' => [
                'validated' => $this->resource['pedagogical_validated'] ?? 0,
                'pending' => $this->resource['pedagogical_pending'] ?? 0,
                'total' => $this->resource['pedagogical_total'] ?? 0,
            ],
            'rates' => [
                'conversion' => $this->resource['conversion_rate'] ?? 0,
                'validation' => $this->resource['validation_rate'] ?? 0,
            ],
            'academic_year' => $this->resource['academic_year'] ?? null,
        ];
    }
}
