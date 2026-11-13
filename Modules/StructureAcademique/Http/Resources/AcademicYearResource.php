<?php

namespace Modules\StructureAcademique\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AcademicYearResource extends JsonResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'status' => $this->resolveStatus(),

            // Relations conditionnelles
            'semesters' => SemesterResource::collection($this->whenLoaded('semesters')),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Statut affiché : Active (année en cours), Terminée (date de fin passée) ou À venir.
     */
    private function resolveStatus(): string
    {
        if ($this->is_active) {
            return 'Active';
        }

        if ($this->end_date && $this->end_date->isPast()) {
            return 'Terminée';
        }

        return 'À venir';
    }
}
