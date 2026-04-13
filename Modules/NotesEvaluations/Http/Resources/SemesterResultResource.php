<?php

namespace Modules\NotesEvaluations\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SemesterResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'semester_id' => $this->semester_id,
            'average' => $this->average,
            'is_final' => $this->is_final,
            'is_validated' => $this->is_validated,
            'status' => $this->status,
            'passed' => $this->passed,
            'mention' => $this->mention,

            // Global Status (Story 14)
            'global_status' => $this->global_status,
            'global_status_label' => $this->global_status_label,
            'status_badge_color' => $this->status_badge_color,

            // Module Counts (Story 14)
            'validated_modules_count' => $this->validated_modules_count,
            'compensated_modules_count' => $this->compensated_modules_count,
            'failed_modules_count' => $this->failed_modules_count,
            'missing_modules_count' => $this->missing_modules_count,

            // Credits
            'total_credits' => $this->total_credits,
            'acquired_credits' => $this->acquired_credits,
            'missing_credits' => $this->missing_credits,
            'success_rate' => $this->success_rate,
            'completion_percentage' => $this->completion_percentage,

            // Ranking (Story 14)
            'rank' => $this->rank,
            'total_ranked' => $this->total_ranked,
            'rank_display' => $this->rank_display,

            // Progression (Story 14)
            'can_progress_next_year' => $this->can_progress_next_year,

            // Eliminatory
            'validation_blocked_by_eliminatory' => $this->validation_blocked_by_eliminatory,
            'blocking_reasons' => $this->blocking_reasons,

            'is_published' => $this->is_published,
            'calculated_at' => $this->calculated_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),

            // Relations
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'matricule' => $this->student->matricule,
                'full_name' => $this->student->full_name ?? $this->student->firstname.' '.$this->student->lastname,
            ]),
            'semester' => $this->whenLoaded('semester', fn () => [
                'id' => $this->semester->id,
                'name' => $this->semester->name,
                'academic_year' => $this->semester->academicYear?->name,
            ]),
            'ects_allocations' => EctsAllocationResource::collection($this->whenLoaded('ectsAllocations')),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
