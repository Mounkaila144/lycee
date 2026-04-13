<?php

namespace Modules\Timetable\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Timetable\Entities\TimetableSlot;

class StoreTimetableSlotRequest extends FormRequest
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
            'is_recurring' => ['boolean'],
            'specific_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            'module_id.required' => 'Le module est obligatoire.',
            'module_id.exists' => 'Le module sélectionné n\'existe pas.',
            'teacher_id.required' => 'L\'enseignant est obligatoire.',
            'teacher_id.exists' => 'L\'enseignant sélectionné n\'existe pas.',
            'group_id.required' => 'Le groupe est obligatoire.',
            'group_id.exists' => 'Le groupe sélectionné n\'existe pas.',
            'room_id.required' => 'La salle est obligatoire.',
            'room_id.exists' => 'La salle sélectionnée n\'existe pas.',
            'semester_id.required' => 'Le semestre est obligatoire.',
            'semester_id.exists' => 'Le semestre sélectionné n\'existe pas.',
            'day_of_week.required' => 'Le jour de la semaine est obligatoire.',
            'day_of_week.in' => 'Le jour doit être l\'un des suivants : '.implode(', ', TimetableSlot::VALID_DAYS),
            'start_time.required' => 'L\'heure de début est obligatoire.',
            'start_time.date_format' => 'Le format de l\'heure de début doit être HH:MM.',
            'end_time.required' => 'L\'heure de fin est obligatoire.',
            'end_time.date_format' => 'Le format de l\'heure de fin doit être HH:MM.',
            'end_time.after' => 'L\'heure de fin doit être après l\'heure de début.',
            'type.required' => 'Le type de séance est obligatoire.',
            'type.in' => 'Le type doit être l\'un des suivants : '.implode(', ', TimetableSlot::VALID_TYPES),
        ];
    }

    /**
     * Préparation des données pour validation
     */
    protected function prepareForValidation(): void
    {
        // Ajouter :00 aux heures si format HH:MM
        if ($this->start_time && strlen($this->start_time) === 5) {
            $this->merge(['start_time' => $this->start_time]);
        }
        if ($this->end_time && strlen($this->end_time) === 5) {
            $this->merge(['end_time' => $this->end_time]);
        }
    }
}
