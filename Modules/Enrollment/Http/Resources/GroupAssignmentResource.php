<?php

namespace Modules\Enrollment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_method' => $this->assignment_method,
            'assignment_reason' => $this->assignment_reason,
            'assigned_at' => $this->assigned_at?->toISOString(),

            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'matricule' => $this->student->matricule,
                'firstname' => $this->student->firstname,
                'lastname' => $this->student->lastname,
                'full_name' => $this->student->full_name,
                'email' => $this->student->email,
            ]),

            'group_id' => $this->group_id,
            'group' => $this->whenLoaded('group', fn () => [
                'id' => $this->group->id,
                'code' => $this->group->code,
                'name' => $this->group->name,
                'type' => $this->group->type,
            ]),

            'module_id' => $this->module_id,
            'module' => $this->whenLoaded('module', fn () => [
                'id' => $this->module->id,
                'code' => $this->module->code,
                'name' => $this->module->name,
            ]),

            'academic_year_id' => $this->academic_year_id,
            'academic_year' => $this->whenLoaded('academicYear', fn () => [
                'id' => $this->academicYear->id,
                'name' => $this->academicYear->name,
            ]),

            'assigned_by' => $this->assigned_by,
            'assigned_by_user' => $this->whenLoaded('assignedByUser', fn () => [
                'id' => $this->assignedByUser->id,
                'name' => $this->assignedByUser->firstname.' '.$this->assignedByUser->lastname,
            ]),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
