<?php

namespace Modules\NotesEvaluations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublishResultsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'publication_type' => ['sometimes', 'string', 'in:provisional,final,deliberation'],
            'programme_id' => ['nullable', 'integer', 'exists:tenant.programmes,id'],
            'level' => ['nullable', 'string', 'in:L1,L2,L3,M1,M2'],
            'send_notifications' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'publication_type.in' => 'Le type de publication doit être: provisional, final ou deliberation.',
            'programme_id.exists' => 'Le programme sélectionné n\'existe pas.',
            'level.in' => 'Le niveau doit être: L1, L2, L3, M1 ou M2.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'publication_type' => 'type de publication',
            'programme_id' => 'programme',
            'level' => 'niveau',
            'send_notifications' => 'envoi de notifications',
            'notes' => 'notes',
        ];
    }
}
