<?php

namespace Modules\NotesEvaluations\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RetakeGradeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'retake_enrollment_id' => $this->retake_enrollment_id,
            'score' => $this->score,
            'is_absent' => $this->is_absent,
            'effective_score' => $this->effective_score,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'new_average' => $this->new_average,
            'is_improved' => $this->is_improved,
            'improvement_amount' => $this->improvement_amount,
            'comment' => $this->comment,
            'entered_at' => $this->entered_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'validated_at' => $this->validated_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),

            // Relations
            'retake_enrollment' => $this->whenLoaded('retakeEnrollment', fn () => [
                'id' => $this->retakeEnrollment->id,
                'original_average' => $this->retakeEnrollment->original_average,
                'module_id' => $this->retakeEnrollment->module_id,
                'semester_id' => $this->retakeEnrollment->semester_id,
                'student' => $this->when(
                    $this->retakeEnrollment->relationLoaded('student'),
                    fn () => [
                        'id' => $this->retakeEnrollment->student->id,
                        'matricule' => $this->retakeEnrollment->student->matricule,
                        'firstname' => $this->retakeEnrollment->student->firstname,
                        'lastname' => $this->retakeEnrollment->student->lastname,
                        'full_name' => $this->retakeEnrollment->student->firstname.' '.$this->retakeEnrollment->student->lastname,
                    ]
                ),
            ]),
            'entered_by' => $this->whenLoaded('enteredByUser', fn () => [
                'id' => $this->enteredByUser->id,
                'name' => $this->enteredByUser->name,
            ]),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
