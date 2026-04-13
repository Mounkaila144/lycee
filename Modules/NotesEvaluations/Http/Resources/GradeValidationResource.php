<?php

namespace Modules\NotesEvaluations\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GradeValidationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'module_id' => $this->module_id,
            'evaluation_id' => $this->evaluation_id,
            'academic_year_id' => $this->academic_year_id,
            'semester_id' => $this->semester_id,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'validated_at' => $this->validated_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'scheduled_publish_at' => $this->scheduled_publish_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'notes' => $this->notes,
            'statistics' => $this->statistics,
            'anomalies' => $this->anomalies,
            'has_anomalies' => $this->hasAnomalies(),

            // Relations conditionnelles
            'module' => $this->whenLoaded('module', fn () => [
                'id' => $this->module->id,
                'code' => $this->module->code,
                'name' => $this->module->name,
            ]),
            'evaluation' => $this->whenLoaded('evaluation', fn () => [
                'id' => $this->evaluation->id,
                'name' => $this->evaluation->name,
                'type' => $this->evaluation->type,
            ]),
            'submitter' => $this->whenLoaded('submitter', fn () => [
                'id' => $this->submitter->id,
                'name' => $this->submitter->name,
            ]),
            'validator' => $this->whenLoaded('validator', fn () => [
                'id' => $this->validator->id,
                'name' => $this->validator->name,
            ]),
            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
            ]),
            'semester' => $this->whenLoaded('semester', fn () => [
                'id' => $this->semester->id,
                'name' => $this->semester->name,
            ]),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
