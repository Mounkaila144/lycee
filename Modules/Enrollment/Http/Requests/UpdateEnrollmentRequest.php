<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Enrollment\Entities\StudentEnrollment;

class UpdateEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handle authorization via middleware/policies
    }

    public function rules(): array
    {
        return [
            'level' => [
                'sometimes',
                'string',
                'in:'.implode(',', StudentEnrollment::VALID_LEVELS),
            ],
            'group_id' => [
                'nullable',
                'integer',
                'exists:tenant.groups,id',
            ],
            'status' => [
                'sometimes',
                'string',
                'in:'.implode(',', StudentEnrollment::VALID_STATUSES),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'level.in' => 'Le niveau doit être L1, L2, L3, M1 ou M2',
            'group_id.exists' => 'Le groupe sélectionné n\'existe pas',
            'status.in' => 'Le statut doit être Actif, Suspendu, Annulé ou Terminé',
            'notes.max' => 'Les notes ne peuvent pas dépasser 1000 caractères',
        ];
    }
}
