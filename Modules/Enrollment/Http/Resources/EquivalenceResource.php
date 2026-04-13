<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EquivalenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'transfer_id' => $this->transfer_id,
            'origin_module_code' => $this->origin_module_code,
            'origin_module_name' => $this->origin_module_name,
            'origin_ects' => $this->origin_ects,
            'origin_hours' => $this->origin_hours,
            'origin_grade' => $this->origin_grade,
            'target_module_id' => $this->target_module_id,
            'equivalence_type' => $this->equivalence_type,
            'equivalence_type_label' => $this->getEquivalenceTypeLabel(),
            'equivalence_percentage' => $this->equivalence_percentage,
            'granted_ects' => $this->granted_ects,
            'granted_grade' => $this->granted_grade,
            'notes' => $this->notes,
            'similarity_score' => $this->similarity_score,
            'status' => $this->status,

            // Computed
            'can_be_validated' => $this->canBeValidated(),
            'is_full' => $this->isFull(),
            'is_partial' => $this->isPartial(),
            'is_none' => $this->isNone(),

            // Relations
            'transfer' => new TransferResource($this->whenLoaded('transfer')),
            'target_module' => $this->whenLoaded('targetModule'),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
