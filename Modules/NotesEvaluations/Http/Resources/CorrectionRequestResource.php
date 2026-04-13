<?php

namespace Modules\NotesEvaluations\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CorrectionRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'grade_id' => $this->grade_id,
            'current_value' => $this->current_value,
            'proposed_value' => $this->proposed_value,
            'current_is_absent' => $this->current_is_absent,
            'proposed_is_absent' => $this->proposed_is_absent,
            'change_display' => $this->getFormattedChange(),
            'reason' => $this->reason,
            'status' => $this->status,
            'review_comment' => $this->review_comment,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_expired' => $this->isExpired(),
            'is_active' => $this->isActive(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            // Relations conditionnelles
            'grade' => $this->whenLoaded('grade', fn () => [
                'id' => $this->grade->id,
                'score' => $this->grade->score,
                'is_absent' => $this->grade->is_absent,
                'status' => $this->grade->status,
                'student' => $this->when($this->grade->relationLoaded('student'), fn () => [
                    'id' => $this->grade->student->id,
                    'matricule' => $this->grade->student->matricule,
                    'full_name' => $this->grade->student->full_name,
                ]),
                'evaluation' => $this->when($this->grade->relationLoaded('evaluation'), fn () => [
                    'id' => $this->grade->evaluation->id,
                    'name' => $this->grade->evaluation->name,
                    'module' => $this->when($this->grade->evaluation->relationLoaded('module'), fn () => [
                        'id' => $this->grade->evaluation->module->id,
                        'code' => $this->grade->evaluation->module->code,
                        'name' => $this->grade->evaluation->module->name,
                    ]),
                ]),
                'history' => $this->when(
                    $this->grade->relationLoaded('history'),
                    fn () => GradeHistoryResource::collection($this->grade->history)
                ),
            ]),
            'requester' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
            ]),
            'reviewer' => $this->whenLoaded('reviewer', fn () => [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ]),
        ];
    }
}
