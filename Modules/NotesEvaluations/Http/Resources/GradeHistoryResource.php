<?php

namespace Modules\NotesEvaluations\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GradeHistoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'grade_id' => $this->grade_id,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'old_is_absent' => $this->old_is_absent,
            'new_is_absent' => $this->new_is_absent,
            'change_type' => $this->change_type,
            'change_display' => $this->getFormattedChange(),
            'reason' => $this->reason,
            'changed_at' => $this->changed_at?->toIso8601String(),
            'changed_by' => $this->whenLoaded('changedBy', fn () => [
                'id' => $this->changedBy->id,
                'name' => $this->changedBy->name,
            ]),
            'ip_address' => $this->ip_address,
        ];
    }
}
