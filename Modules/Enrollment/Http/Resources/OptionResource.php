<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'programme_id' => $this->programme_id,
            'level' => $this->level,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'prerequisites' => $this->prerequisites,
            'is_mandatory' => $this->is_mandatory,
            'choice_start_date' => $this->choice_start_date?->toDateString(),
            'choice_end_date' => $this->choice_end_date?->toDateString(),
            'status' => $this->status,

            // Computed fields
            'is_choice_period_open' => $this->isChoicePeriodOpen(),
            'remaining_capacity' => $this->when(
                $request->has('academic_year_id'),
                fn () => $this->getRemainingCapacity($request->get('academic_year_id'))
            ),

            // Relations
            'programme' => $this->whenLoaded('programme', fn () => [
                'id' => $this->programme->id,
                'code' => $this->programme->code,
                'libelle' => $this->programme->libelle,
            ]),
            'choices_count' => $this->whenCounted('choices'),
            'assignments_count' => $this->whenCounted('assignments'),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
