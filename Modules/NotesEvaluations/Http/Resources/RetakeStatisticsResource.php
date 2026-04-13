<?php

namespace Modules\NotesEvaluations\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RetakeStatisticsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        $data = $this->resource;

        return [
            'total_students' => $data['total_students'] ?? 0,
            'students_with_retakes' => $data['students_with_retakes'] ?? 0,
            'retake_rate' => $data['retake_rate'] ?? 0,
            'total_retakes' => $data['total_retakes'] ?? 0,
            'distribution' => [
                '1_module' => $data['distribution']['1_module'] ?? 0,
                '2_modules' => $data['distribution']['2_modules'] ?? 0,
                '3_plus_modules' => $data['distribution']['3_plus_modules'] ?? 0,
            ],
            'most_failed_modules' => $data['most_failed_modules'] ?? [],
        ];
    }
}
