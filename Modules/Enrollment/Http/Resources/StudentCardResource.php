<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentCardResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'academic_year_id' => $this->academic_year_id,
            'card_number' => $this->card_number,
            'qr_signature' => $this->qr_signature,
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'is_expired' => $this->isExpired(),
            'issued_at' => $this->issued_at?->format('Y-m-d'),
            'valid_until' => $this->valid_until?->format('Y-m-d'),
            'is_duplicate' => $this->is_duplicate,
            'original_card_id' => $this->original_card_id,
            'print_status' => $this->print_status,
            'printed_at' => $this->printed_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'has_pdf' => ! empty($this->pdf_path),

            // Relations
            'student' => new StudentResource($this->whenLoaded('student')),
            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear->id,
                'year' => $this->academicYear->year,
                'label' => $this->academicYear->label ?? $this->academicYear->year,
            ]),
            'original_card' => $this->whenLoaded('originalCard', fn () => [
                'id' => $this->originalCard->id,
                'card_number' => $this->originalCard->card_number,
            ]),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
