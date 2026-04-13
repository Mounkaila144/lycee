<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Enrollment\Entities\Group;

class StoreGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'module_id' => ['required', Rule::exists('tenant.modules', 'id')],
            'program_id' => ['required', Rule::exists('tenant.programmes', 'id')],
            'level' => ['required', Rule::in(Group::VALID_LEVELS)],
            'academic_year_id' => ['required', Rule::exists('tenant.academic_years', 'id')],
            'semester_id' => ['nullable', Rule::exists('tenant.semesters', 'id')],
            'code' => ['required', 'string', 'max:50', Rule::unique('tenant.groups', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Group::VALID_TYPES)],
            'capacity_min' => ['nullable', 'integer', 'min:1', 'max:500'],
            'capacity_max' => ['nullable', 'integer', 'min:1', 'max:500', 'gte:capacity_min'],
            'teacher_id' => ['nullable', Rule::exists('tenant.users', 'id')],
            'room_id' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Group::VALID_STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'module_id.required' => 'Le module est obligatoire',
            'module_id.exists' => 'Le module sélectionné n\'existe pas',
            'program_id.required' => 'Le programme est obligatoire',
            'program_id.exists' => 'Le programme sélectionné n\'existe pas',
            'level.required' => 'Le niveau est obligatoire',
            'level.in' => 'Le niveau doit être L1, L2, L3, M1 ou M2',
            'academic_year_id.required' => 'L\'année académique est obligatoire',
            'code.required' => 'Le code du groupe est obligatoire',
            'code.unique' => 'Ce code de groupe existe déjà',
            'name.required' => 'Le nom du groupe est obligatoire',
            'type.required' => 'Le type de groupe est obligatoire',
            'type.in' => 'Le type doit être CM, TD ou TP',
            'capacity_max.gte' => 'La capacité maximale doit être supérieure ou égale à la capacité minimale',
        ];
    }
}
