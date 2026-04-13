<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CardVerificationResource extends JsonResource
{
    public function toArray($request): array
    {
        $card = $this->resource['card'] ?? null;
        $student = $this->resource['student'] ?? null;

        return [
            'valid' => $this->resource['valid'] ?? false,
            'card' => $card ? [
                'id' => $card->id,
                'card_number' => $card->card_number,
                'status' => $card->status,
                'issued_at' => $card->issued_at?->format('Y-m-d'),
                'valid_until' => $card->valid_until?->format('Y-m-d'),
                'is_duplicate' => $card->is_duplicate,
            ] : null,
            'student' => $student ? [
                'id' => $student->id,
                'matricule' => $student->matricule,
                'firstname' => $student->firstname,
                'lastname' => $student->lastname,
                'full_name' => "{$student->firstname} {$student->lastname}",
                'photo' => $student->photo,
                'program' => $student->program?->name ?? 'N/A',
                'level' => $student->level ?? 'N/A',
                'status' => $student->status,
            ] : null,
            'verified_at' => now()->toIso8601String(),
        ];
    }
}
