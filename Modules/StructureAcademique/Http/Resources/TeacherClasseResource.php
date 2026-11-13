<?php

namespace Modules\StructureAcademique\Http\Resources;

/**
 * Classe vue par un professeur (« Mes classes ») : ClasseResource + rôle du professeur
 * dans la classe (professeur principal) et matières qui lui sont affectées.
 */
class TeacherClasseResource extends ClasseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return parent::toArray($request) + [
            'is_head_teacher' => (bool) $this->resource->getAttribute('is_head_teacher'),
            'subjects' => $this->resource->getAttribute('teacher_subjects') ?? [],
        ];
    }
}
