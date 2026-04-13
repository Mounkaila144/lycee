<?php

namespace Modules\NotesEvaluations\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for grade entry view (teacher's perspective)
 */
class GradeEntryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'student_id' => $this->student_id,
            'matricule' => $this->student->matricule,
            'firstname' => $this->student->firstname,
            'lastname' => $this->student->lastname,
            'full_name' => $this->student->full_name,
            'grade' => $this->when($this->grade !== null, [
                'id' => $this->grade?->id,
                'score' => $this->grade?->score,
                'is_absent' => $this->grade?->is_absent ?? false,
                'comment' => $this->grade?->comment,
                'status' => $this->grade?->status ?? 'Draft',
                'entered_at' => $this->grade?->entered_at?->toIso8601String(),
                'has_history' => $this->grade?->history()->exists() ?? false,
            ]),
            'has_grade' => $this->grade !== null,
        ];
    }
}
