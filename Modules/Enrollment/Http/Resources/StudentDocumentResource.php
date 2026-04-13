<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,

            // Document info
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'filename' => $this->filename,
            'original_filename' => $this->original_filename,
            'file_path' => $this->file_path,
            'file_url' => $this->file_url,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'file_size_human' => $this->file_size_human,

            // Metadata
            'description' => $this->description,
            'is_validated' => $this->is_validated,
            'validated_by' => $this->validated_by,
            'validated_at' => $this->validated_at?->toISOString(),

            // File type checks
            'is_image' => $this->isImage(),
            'is_pdf' => $this->isPdf(),

            // Relationships
            'validator' => $this->whenLoaded('validator', function () {
                return [
                    'id' => $this->validator->id,
                    'name' => $this->validator->name,
                    'email' => $this->validator->email,
                ];
            }),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
