<?php

namespace Modules\Attendance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'classe_id' => ['required_without:timetable_slot_id', 'nullable', 'integer', Rule::exists('tenant.classes', 'id')->whereNull('deleted_at')],
            'timetable_slot_id' => ['required_without:classe_id', 'nullable', 'integer', Rule::exists('tenant.timetable_slots', 'id')],
            'subject_id' => ['nullable', 'integer', Rule::exists('tenant.subjects', 'id')],
            'session_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'method' => ['nullable', 'string', Rule::in(['manual', 'mobile', 'qr_code', 'imported'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'classe_id.required_without' => 'La classe (ou le créneau) est obligatoire.',
            'classe_id.exists' => 'La classe sélectionnée est introuvable.',
            'timetable_slot_id.exists' => 'Le créneau sélectionné est introuvable.',
            'subject_id.exists' => 'La matière sélectionnée est introuvable.',
            'session_date.required' => 'La date de la séance est obligatoire.',
            'end_time.after' => 'L\'heure de fin doit être postérieure à l\'heure de début.',
        ];
    }
}
