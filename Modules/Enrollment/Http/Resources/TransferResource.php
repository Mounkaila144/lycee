<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'student_id' => $this->student_id,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'full_name' => $this->full_name,
            'birthdate' => $this->birthdate?->toIso8601String(),
            'email' => $this->email,
            'phone' => $this->phone,
            'origin_institution' => $this->origin_institution,
            'origin_program' => $this->origin_program,
            'origin_level' => $this->origin_level,
            'target_program_id' => $this->target_program_id,
            'target_level' => $this->target_level,
            'academic_year_id' => $this->academic_year_id,
            'transfer_reason' => $this->transfer_reason,
            'total_ects_claimed' => $this->total_ects_claimed,
            'total_ects_granted' => $this->total_ects_granted,
            'status' => $this->status,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'equivalence_certificate_path' => $this->equivalence_certificate_path,

            // Computed
            'can_be_reviewed' => $this->canBeReviewed(),
            'can_be_validated' => $this->canBeValidated(),
            'can_be_integrated' => $this->canBeIntegrated(),
            'can_be_rejected' => $this->canBeRejected(),

            // Statistics (when loaded)
            'equivalence_statistics' => $this->when(
                $request->has('include_statistics'),
                fn () => $this->getEquivalenceStatistics()
            ),

            // Relations
            'student' => new StudentResource($this->whenLoaded('student')),
            'target_program' => $this->whenLoaded('targetProgram'),
            'academic_year' => $this->whenLoaded('academicYear'),
            'reviewer' => $this->whenLoaded('reviewer'),
            'equivalences' => EquivalenceResource::collection($this->whenLoaded('equivalences')),
            'documents' => TransferDocumentResource::collection($this->whenLoaded('documents')),

            // Counts
            'equivalences_count' => $this->whenCounted('equivalences'),
            'documents_count' => $this->whenCounted('documents'),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
