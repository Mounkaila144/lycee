<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailableModuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'credits_ects' => $this->credits_ects,
            'coefficient' => $this->coefficient,
            'type' => $this->type,
            'level' => $this->level,
            'semester' => $this->semester,
            'hours_cm' => $this->hours_cm,
            'hours_td' => $this->hours_td,
            'hours_tp' => $this->hours_tp,
            'total_hours' => $this->total_hours,
            'is_eliminatory' => $this->is_eliminatory,
            'description' => $this->description,

            // Enrollment status (if set)
            'is_enrolled' => $this->is_enrolled ?? false,
            'is_obligatory' => $this->type === 'Obligatoire',
        ];
    }
}
