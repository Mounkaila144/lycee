<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Enrollment\Entities\StudentCard;

class UpdateCardStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(StudentCard::STATUSES)],
        ];
    }

    public function messages(): array
    {
        $validStatuses = implode(', ', StudentCard::STATUSES);

        return [
            'status.required' => 'Le statut est obligatoire.',
            'status.in' => "Le statut doit être l'un des suivants: {$validStatuses}.",
        ];
    }
}
