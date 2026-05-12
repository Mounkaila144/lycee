<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricule' => $this->matricule,

            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'full_name' => $this->full_name,
            'birthdate' => $this->birthdate?->format('Y-m-d'),
            'birthplace' => $this->birthplace,
            'age' => $this->birthdate ? $this->age : null,
            'sex' => $this->sex,
            'nationality' => $this->nationality,

            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'quarter' => $this->quarter,

            'blood_group' => $this->blood_group,
            'health_notes' => $this->health_notes,

            'photo' => $this->photo,
            'photo_url' => $this->photo_url,

            'status' => $this->status,

            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,

            'documents' => StudentDocumentResource::collection($this->whenLoaded('documents')),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
