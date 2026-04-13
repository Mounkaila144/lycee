<?php

namespace Modules\Timetable\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TimetableChangeResource extends JsonResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'change_summary' => $this->change_summary,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'reason' => $this->reason,

            // Relation utilisateur
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name ?? $this->user->firstname.' '.$this->user->lastname,
            ]),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
