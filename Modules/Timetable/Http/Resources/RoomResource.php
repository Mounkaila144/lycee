<?php

namespace Modules\Timetable\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'full_name' => $this->full_name,
            'type' => $this->type,
            'building' => $this->building,
            'floor' => $this->floor,
            'capacity' => $this->capacity,
            'equipment' => $this->equipment,
            'is_active' => $this->is_active,
            'description' => $this->description,

            // Statistiques conditionnelles
            'occupation' => $this->whenLoaded('timetableSlots', function () {
                return [
                    'total_slots' => $this->timetableSlots->count(),
                ];
            }),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
