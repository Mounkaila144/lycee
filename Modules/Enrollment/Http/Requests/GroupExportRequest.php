<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GroupExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'template' => ['nullable', 'string', 'in:group_list,group_list_complete,group_list_with_photos,attendance_sheet'],
            'orientation' => ['nullable', 'string', 'in:portrait,landscape'],
            'sort_by' => ['nullable', 'string', 'in:lastname,firstname,matricule'],
            'include_email' => ['nullable', 'boolean'],
            'include_phone' => ['nullable', 'boolean'],
            'include_photo' => ['nullable', 'boolean'],
            'include_birthdate' => ['nullable', 'boolean'],
            'session_count' => ['nullable', 'integer', 'min:4', 'max:20'],
            'download' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'template.in' => 'Le template doit être group_list, group_list_complete, group_list_with_photos ou attendance_sheet.',
            'orientation.in' => 'L\'orientation doit être portrait ou landscape.',
            'sort_by.in' => 'Le tri doit être par lastname, firstname ou matricule.',
            'session_count.min' => 'Le nombre de sessions doit être d\'au moins 4.',
            'session_count.max' => 'Le nombre de sessions ne peut pas dépasser 20.',
        ];
    }
}
