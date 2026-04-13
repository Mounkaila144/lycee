<?php

namespace Modules\NotesEvaluations\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EctsAllocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'module_id' => $this->module_id,
            'credits_allocated' => $this->credits_allocated,
            'allocation_type' => $this->allocation_type,
            'allocation_type_label' => $this->allocation_type_label,
            'note' => $this->note,
            'allocated_at' => $this->allocated_at?->toIso8601String(),

            // Relations
            'module' => $this->whenLoaded('module', fn () => [
                'id' => $this->module->id,
                'code' => $this->module->code,
                'name' => $this->module->name,
                'credits_ects' => $this->module->credits_ects,
            ]),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
