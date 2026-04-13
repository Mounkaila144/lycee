<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Enrollment\Entities\Group;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $groupId = $this->route('group') ?? $this->route('id');

        $rules = [
            'module_id' => ['sometimes', Rule::exists('tenant.modules', 'id')],
            'program_id' => ['sometimes', Rule::exists('tenant.programmes', 'id')],
            'level' => ['sometimes', Rule::in(Group::VALID_LEVELS)],
            'semester_id' => ['nullable', Rule::exists('tenant.semesters', 'id')],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('tenant.groups', 'code')->ignore($groupId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(Group::VALID_TYPES)],
            'capacity_min' => ['nullable', 'integer', 'min:1', 'max:500'],
            'capacity_max' => ['nullable', 'integer', 'min:1', 'max:500'],
            'teacher_id' => ['nullable', Rule::exists('tenant.users', 'id')],
            'room_id' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(Group::VALID_STATUSES)],
        ];

        // Only add gte validation if capacity_min is provided
        if ($this->has('capacity_min') && $this->filled('capacity_min')) {
            $rules['capacity_max'][] = 'gte:capacity_min';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'level.in' => 'Le niveau doit être L1, L2, L3, M1 ou M2',
            'code.unique' => 'Ce code de groupe existe déjà',
            'type.in' => 'Le type doit être CM, TD ou TP',
            'capacity_max.gte' => 'La capacité maximale doit être supérieure ou égale à la capacité minimale',
        ];
    }
}
