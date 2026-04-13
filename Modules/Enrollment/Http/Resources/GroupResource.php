<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'level' => $this->level,
            'status' => $this->status,

            'capacity' => [
                'min' => $this->capacity_min,
                'max' => $this->capacity_max,
                'current' => $this->current_count,
                'available' => $this->available_slots,
            ],
            'fill_rate' => $this->fill_rate,
            'is_full' => $this->is_full,
            'is_below_minimum' => $this->is_below_minimum,

            'module_id' => $this->module_id,
            'module' => $this->whenLoaded('module', fn () => [
                'id' => $this->module->id,
                'code' => $this->module->code,
                'name' => $this->module->name,
            ]),

            'program_id' => $this->program_id,
            'programme' => $this->whenLoaded('programme', fn () => [
                'id' => $this->programme->id,
                'code' => $this->programme->code ?? null,
                'libelle' => $this->programme->libelle,
            ]),

            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
            ]),

            'semester_id' => $this->semester_id,
            'semester' => $this->whenLoaded('semester', fn () => [
                'id' => $this->semester->id,
                'name' => $this->semester->name,
            ]),

            'teacher_id' => $this->teacher_id,
            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->teacher->id,
                'name' => $this->teacher->firstname.' '.$this->teacher->lastname,
                'email' => $this->teacher->email,
            ]),

            'room_id' => $this->room_id,

            'assignments' => GroupAssignmentResource::collection($this->whenLoaded('assignments')),
            'assignments_count' => $this->whenCounted('assignments'),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
