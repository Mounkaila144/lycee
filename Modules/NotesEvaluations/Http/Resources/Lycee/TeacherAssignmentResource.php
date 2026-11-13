<?php

namespace Modules\NotesEvaluations\Http\Resources\Lycee;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\NotesEvaluations\Entities\TeacherAssignment;

/**
 * @mixin TeacherAssignment
 */
class TeacherAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'teacher' => $this->teacher ? [
                'id' => $this->teacher->id,
                'full_name' => trim("{$this->teacher->firstname} {$this->teacher->lastname}"),
            ] : null,
            'classe' => $this->classe ? [
                'id' => $this->classe->id,
                'name' => $this->classe->name,
            ] : null,
            'subject' => $this->subject ? [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
                'code' => $this->subject->code,
            ] : null,
            'academic_year_id' => $this->academic_year_id,
            'students_count' => $this->when(array_key_exists('students_count', $this->resource->getAttributes()), fn (): int => (int) $this->resource->getAttribute('students_count')),
            'coefficient' => $this->when(array_key_exists('coefficient', $this->resource->getAttributes()), fn (): float => (float) $this->resource->getAttribute('coefficient')),
            'is_head_teacher' => $this->when(array_key_exists('is_head_teacher', $this->resource->getAttributes()), fn (): bool => (bool) $this->resource->getAttribute('is_head_teacher')),
        ];
    }
}
