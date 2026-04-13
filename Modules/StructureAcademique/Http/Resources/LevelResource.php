<?php

namespace Modules\StructureAcademique\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LevelResource extends JsonResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'cycle_id' => $this->cycle_id,
            'code' => $this->code,
            'name' => $this->name,
            'order_index' => $this->order_index,

            // Relations conditionnelles
            'cycle' => new CycleResource($this->whenLoaded('cycle')),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
