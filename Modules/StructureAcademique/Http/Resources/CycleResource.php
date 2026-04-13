<?php

namespace Modules\StructureAcademique\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CycleResource extends JsonResource
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
            'description' => $this->description,
            'display_order' => $this->display_order,
            'is_active' => $this->is_active,

            // Relations conditionnelles
            'levels' => LevelResource::collection($this->whenLoaded('levels')),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
