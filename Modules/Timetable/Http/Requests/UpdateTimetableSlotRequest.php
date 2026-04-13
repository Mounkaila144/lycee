<?php

namespace Modules\Timetable\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Timetable\Entities\TimetableSlot;

class UpdateTimetableSlotRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation
     */
    public function rules(): array
    {
        return [
            'module_id' => ['sometimes', 'required', 'integer', 'exists:tenant.modules,id'],
            'teacher_id' => ['sometimes', 'required', 'integer', 'exists:tenant.users,id'],
            'group_id' => ['sometimes', 'required', 'integer', 'exists:tenant.groups,id'],
            'room_id' => ['sometimes', 'required', 'integer', 'exists:tenant.rooms,id'],
            'semester_id' => ['sometimes', 'required', 'integer', 'exists:tenant.semesters,id'],
            'day_of_week' => ['sometimes', 'required', 'string', 'in:'.implode(',', TimetableSlot::VALID_DAYS)],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i', 'after:start_time'],
            'type' => ['sometimes', 'required', 'string', 'in:'.implode(',', TimetableSlot::VALID_TYPES)],
            'is_recurring' => ['boolean'],
            'specific_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            'module_id.exists' => 'Le module sélectionné n\'existe pas.',
            'teacher_id.exists' => 'L\'enseignant sélectionné n\'existe pas.',
            'group_id.exists' => 'Le groupe sélectionné n\'existe pas.',
            'room_id.exists' => 'La salle sélectionnée n\'existe pas.',
            'semester_id.exists' => 'Le semestre sélectionné n\'existe pas.',
            'day_of_week.in' => 'Le jour doit être l\'un des suivants : '.implode(', ', TimetableSlot::VALID_DAYS),
            'start_time.date_format' => 'Le format de l\'heure de début doit être HH:MM.',
            'end_time.date_format' => 'Le format de l\'heure de fin doit être HH:MM.',
            'end_time.after' => 'L\'heure de fin doit être après l\'heure de début.',
            'type.in' => 'Le type doit être l\'un des suivants : '.implode(', ', TimetableSlot::VALID_TYPES),
        ];
    }
}
