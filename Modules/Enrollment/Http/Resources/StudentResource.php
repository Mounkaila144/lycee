<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricule' => $this->matricule,

            // Personal information
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'full_name' => $this->full_name,
            'birthdate' => $this->birthdate?->format('Y-m-d'),
            'birthplace' => $this->birthplace,
            'age' => $this->age,
            'sex' => $this->sex,
            'nationality' => $this->nationality,

            // Contact
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,

            // Address
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,

            // Photo
            'photo' => $this->photo,
            'photo_url' => $this->photo_url,

            // Status
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'is_suspended' => $this->isSuspended(),
            'is_excluded' => $this->isExcluded(),
            'is_graduated' => $this->isGraduated(),

            // Emergency contact
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,

            // Documents
            'documents' => StudentDocumentResource::collection($this->whenLoaded('documents')),
            'documents_count' => $this->whenCounted('documents'),
            'has_complete_documents' => $this->when(
                $this->relationLoaded('documents'),
                fn () => $this->hasCompleteDocuments()
            ),
            'completeness_percentage' => $this->when(
                $this->relationLoaded('documents'),
                fn () => $this->getCompletenessPercentage()
            ),
            'missing_documents' => $this->when(
                $this->relationLoaded('documents'),
                fn () => $this->getMissingDocuments()
            ),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
