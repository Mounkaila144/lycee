<?php

namespace Modules\NotesEvaluations\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GradeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'evaluation_id' => $this->evaluation_id,
            'score' => $this->score,
            'is_absent' => $this->is_absent,
            'comment' => $this->comment,
            'status' => $this->status,
            'is_visible_to_students' => $this->is_visible_to_students,
            'entered_at' => $this->entered_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),

            // Relations conditionnelles
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'matricule' => $this->student->matricule,
                'firstname' => $this->student->firstname,
                'lastname' => $this->student->lastname,
                'full_name' => $this->student->full_name,
            ]),
            'evaluation' => $this->whenLoaded('evaluation', fn () => [
                'id' => $this->evaluation->id,
                'name' => $this->evaluation->name,
                'type' => $this->evaluation->type,
                'coefficient' => $this->evaluation->coefficient,
                'max_score' => $this->evaluation->max_score,
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
