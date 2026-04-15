<?php

namespace Modules\StructureAcademique\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AcademicPeriodResource extends JsonResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'semester_id' => $this->semester_id,
            'name' => $this->name,
            'type' => $this->type,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'description' => $this->description,

            // Relations
            'semester' => $this->whenLoaded('semester', function () {
                return [
                    'id' => $this->semester->id,
                    'name' => $this->semester->name,
                    'academic_year_id' => $this->semester->academic_year_id,
                ];
            }),

            // Computed
            'is_active' => $this->start_date && $this->end_date
                ? now()->between($this->start_date, $this->end_date)
                : false,
            'is_upcoming' => $this->start_date ? $this->start_date->isFuture() : false,
            'is_past' => $this->end_date ? $this->end_date->isPast() : false,

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
