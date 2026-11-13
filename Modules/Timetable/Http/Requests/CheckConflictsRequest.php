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
            'subject_id' => ['nullable', 'integer', 'exists:tenant.subjects,id'],
            'module_id' => ['nullable', 'integer', 'exists:tenant.modules,id'],
            'teacher_id' => ['required', 'integer', 'exists:tenant.users,id'],
            'classe_id' => ['required_without:group_id', 'nullable', 'integer', 'exists:tenant.classes,id'],
            'group_id' => ['required_without:classe_id', 'nullable', 'integer', 'exists:tenant.groups,id'],
            'room_id' => ['nullable', 'integer', 'exists:tenant.rooms,id'],
            'semester_id' => ['required', 'integer', 'exists:tenant.semesters,id'],
            'day_of_week' => ['required', 'string', 'in:'.implode(',', TimetableSlot::VALID_DAYS)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'type' => ['sometimes', 'string', 'in:'.implode(',', TimetableSlot::VALID_TYPES)],
            'exclude_slot_id' => ['nullable', 'integer', 'exists:tenant.timetable_slots,id'],
        ];
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            'teacher_id.required' => 'L\'enseignant est obligatoire pour la vérification.',
            'classe_id.required_without' => 'La classe est obligatoire pour la vérification.',
            'group_id.required_without' => 'Le groupe est obligatoire pour la vérification.',
            'semester_id.required' => 'Le semestre est obligatoire pour la vérification.',
            'day_of_week.required' => 'Le jour de la semaine est obligatoire.',
            'start_time.required' => 'L\'heure de début est obligatoire.',
            'end_time.required' => 'L\'heure de fin est obligatoire.',
            'end_time.after' => 'L\'heure de fin doit être après l\'heure de début.',
        ];
    }
}
