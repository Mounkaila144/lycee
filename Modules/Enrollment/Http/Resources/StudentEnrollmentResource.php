<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentEnrollmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Student info
            'student_id' => $this->student_id,
            'student' => new StudentResource($this->whenLoaded('student')),

            // Programme info
            'programme_id' => $this->programme_id,
            'programme' => $this->whenLoaded('programme', fn () => [
                'id' => $this->programme->id,
                'code' => $this->programme->code,
                'libelle' => $this->programme->libelle,
                'type' => $this->programme->type,
            ]),

            // Academic year
            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
                'is_active' => $this->academicYear->is_active,
            ]),

            // Semester
            'semester_id' => $this->semester_id,
            'semester' => $this->whenLoaded('semester', fn () => [
                'id' => $this->semester->id,
                'name' => $this->semester->name,
                'start_date' => $this->semester->start_date?->format('Y-m-d'),
                'end_date' => $this->semester->end_date?->format('Y-m-d'),
            ]),

            // Level and group
            'level' => $this->level,
            'group_id' => $this->group_id,

            // Enrollment details
            'enrollment_date' => $this->enrollment_date?->format('Y-m-d'),
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'notes' => $this->notes,

            // Module enrollments
            'module_enrollments' => StudentModuleEnrollmentResource::collection(
                $this->whenLoaded('moduleEnrollments')
            ),
            'module_enrollments_count' => $this->whenCounted('moduleEnrollments'),

            // Computed fields
            'total_credits' => $this->when(
                $this->relationLoaded('moduleEnrollments'),
                fn () => $this->total_credits
            ),
            'enrolled_modules_count' => $this->when(
                $this->relationLoaded('moduleEnrollments'),
                fn () => $this->enrolled_modules_count
            ),

            // Enrolled by
            'enrolled_by' => $this->enrolled_by,
            'enrolled_by_user' => $this->whenLoaded('enrolledBy', fn () => [
                'id' => $this->enrolledBy->id,
                'firstname' => $this->enrolledBy->firstname,
                'lastname' => $this->enrolledBy->lastname,
            ]),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
