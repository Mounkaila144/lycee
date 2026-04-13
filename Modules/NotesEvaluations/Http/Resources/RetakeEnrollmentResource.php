<?php

namespace Modules\NotesEvaluations\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RetakeEnrollmentResource extends JsonResource
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
            'semester_id' => $this->semester_id,
            'original_average' => $this->original_average,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_color' => $this->status_color,
            'gap_to_validation' => $this->gap_to_validation,
            'identified_at' => $this->identified_at?->toIso8601String(),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),

            // Relations
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'matricule' => $this->student->matricule,
                'firstname' => $this->student->firstname,
                'lastname' => $this->student->lastname,
                'full_name' => $this->student->firstname.' '.$this->student->lastname,
                'email' => $this->student->email,
            ]),
            'module' => $this->whenLoaded('module', fn () => [
                'id' => $this->module->id,
                'code' => $this->module->code,
                'name' => $this->module->name,
                'credits_ects' => $this->module->credits_ects,
                'is_eliminatory' => $this->module->is_eliminatory ?? false,
            ]),
            'semester' => $this->whenLoaded('semester', fn () => [
                'id' => $this->semester->id,
                'name' => $this->semester->name,
                'academic_year_id' => $this->semester->academic_year_id,
            ]),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
