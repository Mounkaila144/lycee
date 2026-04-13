<?php

namespace Modules\Timetable\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TimetableSlotResource extends JsonResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'day_of_week' => $this->day_of_week,
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'time_range' => $this->time_range,
            'type' => $this->type,
            'is_recurring' => $this->is_recurring,
            'specific_date' => $this->specific_date?->toDateString(),
            'notes' => $this->notes,
            'duration_minutes' => $this->duration,
            'duration_hours' => $this->duration_hours,
            'display_name' => $this->display_name,

            // Relations
            'module' => $this->whenLoaded('module', fn () => [
                'id' => $this->module->id,
                'code' => $this->module->code,
                'name' => $this->module->name,
            ]),

            'teacher' => $this->whenLoaded('teacher', fn () => [
                'id' => $this->teacher->id,
                'name' => $this->teacher->name ?? $this->teacher->firstname.' '.$this->teacher->lastname,
            ]),

            'group' => $this->whenLoaded('group', fn () => [
                'id' => $this->group->id,
                'code' => $this->group->code,
                'name' => $this->group->name,
                'type' => $this->group->type,
            ]),

            'room' => $this->whenLoaded('room', fn () => new RoomResource($this->room)),

            'semester' => $this->whenLoaded('semester', fn () => [
                'id' => $this->semester->id,
                'name' => $this->semester->name,
            ]),

            // Historique des modifications
            'changes_count' => $this->whenLoaded('changes', fn () => $this->changes->count()),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
