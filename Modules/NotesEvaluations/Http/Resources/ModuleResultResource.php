<?php

namespace Modules\NotesEvaluations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'module_id' => $this->module_id,
            'semester_id' => $this->semester_id,
            'statistics' => [
                'total_students' => $this->total_students,
                'class_average' => $this->class_average,
                'min_grade' => $this->min_grade,
                'max_grade' => $this->max_grade,
                'median' => $this->median,
                'standard_deviation' => $this->standard_deviation,
                'pass_rate' => $this->pass_rate,
                'absence_rate' => $this->absence_rate,
            ],
            'distribution' => $this->distribution,
            'pass_count' => $this->pass_count,
            'fail_count' => $this->fail_count,
            'is_published' => $this->is_published,
            'generated_at' => $this->generated_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'module' => $this->whenLoaded('module', fn () => [
                'id' => $this->module->id,
                'code' => $this->module->code,
                'name' => $this->module->name,
            ]),
            'semester' => $this->whenLoaded('semester', fn () => [
                'id' => $this->semester->id,
                'name' => $this->semester->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
