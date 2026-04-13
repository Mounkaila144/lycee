<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReenrollmentCampaignResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'from_academic_year_id' => $this->from_academic_year_id,
            'to_academic_year_id' => $this->to_academic_year_id,
            'start_date' => $this->start_date?->toIso8601String(),
            'end_date' => $this->end_date?->toIso8601String(),
            'eligible_programs' => $this->eligible_programs,
            'eligible_levels' => $this->eligible_levels,
            'required_documents' => $this->required_documents,
            'fees_config' => $this->fees_config,
            'min_ects_required' => $this->min_ects_required,
            'check_financial_clearance' => $this->check_financial_clearance,
            'status' => $this->status,
            'description' => $this->description,
            'is_open' => $this->isOpen(),

            // Relations
            'from_academic_year' => $this->whenLoaded('fromAcademicYear'),
            'to_academic_year' => $this->whenLoaded('toAcademicYear'),

            // Statistics (optional)
            'statistics' => $this->when(
                $request->has('include_statistics'),
                fn () => $this->getStatistics()
            ),

            // Counts
            'reenrollments_count' => $this->whenCounted('reenrollments'),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
