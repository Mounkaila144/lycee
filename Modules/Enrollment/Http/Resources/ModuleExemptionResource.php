<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ModuleExemptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'exemption_number' => $this->exemption_number,
            'student_id' => $this->student_id,
            'module_id' => $this->module_id,
            'academic_year_id' => $this->academic_year_id,
            'exemption_type' => $this->exemption_type,
            'exemption_type_label' => $this->getExemptionTypeLabel(),
            'reason_category' => $this->reason_category,
            'reason_category_label' => $this->getReasonCategoryLabel(),
            'reason_details' => $this->reason_details,
            'uploaded_documents' => $this->uploaded_documents,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'reviewed_by_teacher' => $this->reviewed_by_teacher,
            'teacher_opinion' => $this->teacher_opinion,
            'teacher_reviewed_at' => $this->teacher_reviewed_at?->toIso8601String(),
            'validated_by' => $this->validated_by,
            'validation_notes' => $this->validation_notes,
            'validated_at' => $this->validated_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'grants_ects' => $this->grants_ects,
            'ects_granted' => $this->ects_granted,
            'grade_granted' => $this->grade_granted,
            'certificate_path' => $this->certificate_path,
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'revoked_by' => $this->revoked_by,
            'revocation_reason' => $this->revocation_reason,

            // Computed
            'can_be_reviewed' => $this->canBeReviewed(),
            'can_be_validated' => $this->canBeValidated(),
            'can_be_revoked' => $this->canBeRevoked(),
            'is_active' => $this->isActive(),

            // Relations
            'student' => new StudentResource($this->whenLoaded('student')),
            'module' => $this->whenLoaded('module'),
            'academic_year' => $this->whenLoaded('academicYear'),
            'teacher_reviewer' => $this->whenLoaded('teacherReviewer'),
            'validator' => $this->whenLoaded('validator'),
            'revoked_by_user' => $this->whenLoaded('revokedByUser'),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
