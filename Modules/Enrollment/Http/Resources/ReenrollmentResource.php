<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReenrollmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'campaign_id' => $this->campaign_id,
            'student_id' => $this->student_id,
            'previous_enrollment_id' => $this->previous_enrollment_id,
            'previous_level' => $this->previous_level,
            'target_level' => $this->target_level,
            'target_level_label' => $this->getTargetLevelLabel(),
            'target_program_id' => $this->target_program_id,
            'is_redoing' => $this->is_redoing,
            'is_reorientation' => $this->is_reorientation,
            'personal_data_updates' => $this->personal_data_updates,
            'uploaded_documents' => $this->uploaded_documents,
            'has_accepted_rules' => $this->has_accepted_rules,
            'eligibility_status' => $this->eligibility_status,
            'eligibility_notes' => $this->eligibility_notes,
            'status' => $this->status,
            'validated_by' => $this->validated_by,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'validated_at' => $this->validated_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'confirmation_pdf_path' => $this->confirmation_pdf_path,
            'new_enrollment_id' => $this->new_enrollment_id,

            // Computed
            'can_be_submitted' => $this->canBeSubmitted(),
            'can_be_validated' => $this->canBeValidated(),
            'can_be_rejected' => $this->canBeRejected(),

            // Relations
            'campaign' => new ReenrollmentCampaignResource($this->whenLoaded('campaign')),
            'student' => new StudentResource($this->whenLoaded('student')),
            'target_program' => $this->whenLoaded('targetProgram'),
            'validator' => $this->whenLoaded('validator'),
            'previous_enrollment' => $this->whenLoaded('previousEnrollment'),
            'new_enrollment' => $this->whenLoaded('newEnrollment'),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
