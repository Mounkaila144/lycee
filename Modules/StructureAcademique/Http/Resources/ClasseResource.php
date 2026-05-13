<?php

namespace Modules\StructureAcademique\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClasseResource extends JsonResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'section' => $this->section,
            'max_capacity' => $this->max_capacity,
            'classroom' => $this->classroom,
            'academic_year_id' => $this->academic_year_id,
            'level_id' => $this->level_id,
            'series_id' => $this->series_id,
            'head_teacher_id' => $this->head_teacher_id,

            // Relations conditionnelles
            'level' => new LevelResource($this->whenLoaded('level')),
            'series' => new SeriesResource($this->whenLoaded('series')),
            'head_teacher' => $this->whenLoaded('headTeacher', function () {
                return [
                    'id' => $this->headTeacher->id,
                    'firstname' => $this->headTeacher->firstname,
                    'lastname' => $this->headTeacher->lastname,
                    'name' => $this->headTeacher->name,
                ];
            }),
            'academic_year' => new AcademicYearResource($this->whenLoaded('academicYear')),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
