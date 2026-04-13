<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentModuleEnrollmentResource extends JsonResource
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

            // Enrollment reference
            'student_enrollment_id' => $this->student_enrollment_id,

            // Module info
            'module_id' => $this->module_id,
            'module' => $this->whenLoaded('module', fn () => [
                'id' => $this->module->id,
                'code' => $this->module->code,
                'name' => $this->module->name,
                'credits_ects' => $this->module->credits_ects,
                'coefficient' => $this->module->coefficient,
                'type' => $this->module->type,
                'level' => $this->module->level,
                'semester' => $this->module->semester,
                'hours_cm' => $this->module->hours_cm,
                'hours_td' => $this->module->hours_td,
                'hours_tp' => $this->module->hours_tp,
                'total_hours' => $this->module->total_hours,
                'is_eliminatory' => $this->module->is_eliminatory,
            ]),

            // Semester
            'semester_id' => $this->semester_id,
            'semester' => $this->whenLoaded('semester', fn () => [
                'id' => $this->semester->id,
                'name' => $this->semester->name,
            ]),

            // Enrollment details
            'enrollment_date' => $this->enrollment_date?->format('Y-m-d'),
            'status' => $this->status,
            'is_optional' => $this->is_optional,
            'notes' => $this->notes,

            // Computed fields
            'credits' => $this->when(
                $this->relationLoaded('module'),
                fn () => $this->credits
            ),
            'module_code' => $this->when(
                $this->relationLoaded('module'),
                fn () => $this->module_code
            ),
            'module_name' => $this->when(
                $this->relationLoaded('module'),
                fn () => $this->module_name
            ),

            // Status checks
            'is_enrolled' => $this->isEnrolled(),
            'is_validated' => $this->isValidated(),
            'can_be_removed' => $this->canBeRemoved(),

            // Enrolled by
            'enrolled_by' => $this->enrolled_by,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
