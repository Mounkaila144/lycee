<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PedagogicalEnrollmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'program_id' => $this->program_id,
            'level' => $this->level,
            'academic_year_id' => $this->academic_year_id,
            'semester_id' => $this->semester_id,
            'status' => $this->status,
            'total_modules' => $this->total_modules,
            'total_ects' => $this->total_ects,

            // Check flags
            'modules_check' => $this->modules_check,
            'groups_check' => $this->groups_check,
            'options_check' => $this->options_check,
            'prerequisites_check' => $this->prerequisites_check,
            'is_complete' => $this->isComplete(),

            // Validation info
            'validated_by' => $this->validated_by,
            'validated_at' => $this->validated_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'contract_pdf_path' => $this->contract_pdf_path,
            'has_contract' => ! empty($this->contract_pdf_path),

            // Relations
            'student' => new StudentResource($this->whenLoaded('student')),
            'program' => $this->whenLoaded('program', fn () => [
                'id' => $this->program->id,
                'code' => $this->program->code,
                'name' => $this->program->name,
            ]),
            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear->id,
                'year' => $this->academicYear->year,
                'label' => $this->academicYear->label ?? $this->academicYear->year,
            ]),
            'semester' => $this->whenLoaded('semester', fn () => [
                'id' => $this->semester->id,
                'name' => $this->semester->name,
            ]),
            'validator' => $this->whenLoaded('validator', fn () => [
                'id' => $this->validator->id,
                'name' => $this->validator->name,
            ]),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
