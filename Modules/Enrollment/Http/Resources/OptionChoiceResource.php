<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OptionChoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'option_id' => $this->option_id,
            'academic_year_id' => $this->academic_year_id,
            'choice_rank' => $this->choice_rank,
            'choice_rank_label' => $this->getChoiceRankLabel(),
            'status' => $this->status,
            'motivation' => $this->motivation,

            // Relations
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'matricule' => $this->student->matricule,
                'firstname' => $this->student->firstname,
                'lastname' => $this->student->lastname,
                'full_name' => $this->student->full_name,
            ]),
            'option' => $this->whenLoaded('option', fn () => new OptionResource($this->option)),
            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
            ]),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
