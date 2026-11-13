<?php

namespace Modules\NotesEvaluations\Http\Resources\Lycee;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\NotesEvaluations\Entities\ClassEvaluation;

/**
 * @mixin ClassEvaluation
 */
class ClassEvaluationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $average = $this->resource->getAttribute('average');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'evaluation_date' => $this->evaluation_date?->format('Y-m-d'),
            'max_score' => (float) $this->max_score,
            'weight' => (float) $this->weight,
            'status' => $this->status,
            'classe' => $this->classe ? ['id' => $this->classe->id, 'name' => $this->classe->name] : null,
            'subject' => $this->subject ? [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
                'code' => $this->subject->code,
            ] : null,
            'semester' => $this->semester ? ['id' => $this->semester->id, 'name' => $this->semester->name] : null,
            'teacher' => $this->teacher ? [
                'id' => $this->teacher->id,
                'full_name' => trim("{$this->teacher->firstname} {$this->teacher->lastname}"),
            ] : null,
            'grades_count' => (int) ($this->resource->getAttribute('grades_count') ?? 0),
            'students_count' => (int) ($this->classe?->getAttribute('students_count') ?? 0),
            'average' => $average !== null ? round((float) $average, 2) : null,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
