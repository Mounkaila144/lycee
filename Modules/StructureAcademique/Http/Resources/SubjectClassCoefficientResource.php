<?php

namespace Modules\StructureAcademique\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SubjectClassCoefficientResource extends JsonResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'subject_id' => $this->subject_id,
            'level_id' => $this->level_id,
            'series_id' => $this->series_id,
            'coefficient' => (float) $this->coefficient,
            'hours_per_week' => $this->hours_per_week,

            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'level' => new LevelResource($this->whenLoaded('level')),
            'series' => new SeriesResource($this->whenLoaded('series')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
