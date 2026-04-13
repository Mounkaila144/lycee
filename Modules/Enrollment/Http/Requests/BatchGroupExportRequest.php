<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchGroupExportRequest extends FormRequest
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
            'group_ids' => ['required_without_all:module_id,teacher_id', 'array'],
            'group_ids.*' => ['integer', 'exists:tenant.groups,id'],
            'module_id' => ['nullable', 'integer', 'exists:tenant.modules,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:tenant.users,id'],
            'format' => ['nullable', 'string', 'in:pdf,excel'],
            'template' => ['nullable', 'string', 'in:group_list,group_list_complete,group_list_with_photos'],
            'orientation' => ['nullable', 'string', 'in:portrait,landscape'],
            'sort_by' => ['nullable', 'string', 'in:lastname,firstname,matricule'],
            'include_email' => ['nullable', 'boolean'],
            'include_phone' => ['nullable', 'boolean'],
            'include_photo' => ['nullable', 'boolean'],
            'download' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'group_ids.required_without_all' => 'Veuillez sélectionner des groupes ou spécifier un module ou enseignant.',
            'group_ids.*.exists' => 'Un ou plusieurs groupes sélectionnés n\'existent pas.',
            'module_id.exists' => 'Le module sélectionné n\'existe pas.',
            'teacher_id.exists' => 'L\'enseignant sélectionné n\'existe pas.',
            'format.in' => 'Le format doit être pdf ou excel.',
            'template.in' => 'Le template n\'est pas valide.',
            'orientation.in' => 'L\'orientation doit être portrait ou landscape.',
            'sort_by.in' => 'Le tri doit être par lastname, firstname ou matricule.',
        ];
    }
}
