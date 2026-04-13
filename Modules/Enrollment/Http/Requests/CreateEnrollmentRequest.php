<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Enrollment\Entities\StudentEnrollment;

class CreateEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handle authorization via middleware/policies
    }

    public function rules(): array
    {
        return [
            'student_id' => [
                'required',
                'integer',
                'exists:tenant.students,id',
            ],
            'programme_id' => [
                'required',
                'integer',
                'exists:tenant.programmes,id',
            ],
            'semester_id' => [
                'required',
                'integer',
                'exists:tenant.semesters,id',
            ],
            'level' => [
                'required',
                'string',
                'in:'.implode(',', StudentEnrollment::VALID_LEVELS),
            ],
            'group_id' => [
                'nullable',
                'integer',
                'exists:tenant.groups,id',
            ],
            'module_ids' => [
                'nullable',
                'array',
            ],
            'module_ids.*' => [
                'integer',
                'exists:tenant.modules,id',
            ],
            'auto_enroll_obligatory' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'L\'étudiant est obligatoire',
            'student_id.exists' => 'L\'étudiant sélectionné n\'existe pas',
            'programme_id.required' => 'Le programme est obligatoire',
            'programme_id.exists' => 'Le programme sélectionné n\'existe pas',
            'semester_id.required' => 'Le semestre est obligatoire',
            'semester_id.exists' => 'Le semestre sélectionné n\'existe pas',
            'level.required' => 'Le niveau est obligatoire',
            'level.in' => 'Le niveau doit être L1, L2, L3, M1 ou M2',
            'group_id.exists' => 'Le groupe sélectionné n\'existe pas',
            'module_ids.array' => 'Les modules doivent être un tableau',
            'module_ids.*.exists' => 'Un des modules sélectionnés n\'existe pas',
        ];
    }

    public function getAutoEnrollObligatory(): bool
    {
        return $this->input('auto_enroll_obligatory', true);
    }
}
