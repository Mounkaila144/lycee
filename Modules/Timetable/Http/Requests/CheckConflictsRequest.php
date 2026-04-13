<?php

namespace Modules\Timetable\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Timetable\Entities\TimetableSlot;

class CheckConflictsRequest extends FormRequest
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
            'module_id' => ['required', 'integer', 'exists:tenant.modules,id'],
            'teacher_id' => ['required', 'integer', 'exists:tenant.users,id'],
            'group_id' => ['required', 'integer', 'exists:tenant.groups,id'],
            'room_id' => ['required', 'integer', 'exists:tenant.rooms,id'],
            'semester_id' => ['required', 'integer', 'exists:tenant.semesters,id'],
            'day_of_week' => ['required', 'string', 'in:'.implode(',', TimetableSlot::VALID_DAYS)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'type' => ['required', 'string', 'in:'.implode(',', TimetableSlot::VALID_TYPES)],
            'exclude_slot_id' => ['nullable', 'integer', 'exists:tenant.timetable_slots,id'],
        ];
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            'module_id.required' => 'Le module est obligatoire pour la vérification.',
            'teacher_id.required' => 'L\'enseignant est obligatoire pour la vérification.',
            'group_id.required' => 'Le groupe est obligatoire pour la vérification.',
            'room_id.required' => 'La salle est obligatoire pour la vérification.',
            'semester_id.required' => 'Le semestre est obligatoire pour la vérification.',
            'day_of_week.required' => 'Le jour de la semaine est obligatoire.',
            'start_time.required' => 'L\'heure de début est obligatoire.',
            'end_time.required' => 'L\'heure de fin est obligatoire.',
            'end_time.after' => 'L\'heure de fin doit être après l\'heure de début.',
            'type.required' => 'Le type de séance est obligatoire.',
        ];
    }
}
