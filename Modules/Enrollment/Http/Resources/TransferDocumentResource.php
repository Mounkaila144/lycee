<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransferDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'transfer_id' => $this->transfer_id,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'original_name' => $this->original_name,
            'path' => $this->path,
            'url' => $this->url,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'size_formatted' => $this->size_formatted,
            'is_pdf' => $this->isPdf(),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
