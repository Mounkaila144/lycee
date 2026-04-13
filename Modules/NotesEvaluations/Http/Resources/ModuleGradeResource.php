<?php

namespace Modules\NotesEvaluations\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ModuleGradeResource extends JsonResource
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
            'average' => $this->average,
            'is_final' => $this->is_final,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'is_validated' => $this->is_validated,
            'missing_evaluations_count' => $this->missing_evaluations_count,
            'rank' => $this->rank,
            'total_ranked' => $this->total_ranked,
            'rank_display' => $this->rank_display,
            'mention' => $this->mention,
            'calculated_at' => $this->calculated_at?->toIso8601String(),

            // Relations
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'matricule' => $this->student->matricule,
                'full_name' => $this->student->full_name ?? $this->student->firstname.' '.$this->student->lastname,
            ]),
            'module' => $this->whenLoaded('module', fn () => [
                'id' => $this->module->id,
                'code' => $this->module->code,
                'name' => $this->module->name,
                'credits_ects' => $this->module->credits_ects,
                'is_eliminatory' => $this->module->is_eliminatory,
            ]),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
