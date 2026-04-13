<?php

namespace Modules\Timetable\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Timetable\Entities\Room;

class UpdateRoomRequest extends FormRequest
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
        $roomId = $this->route('room')?->id ?? $this->route('room');

        return [
            'code' => ['sometimes', 'required', 'string', 'max:50', 'unique:tenant.rooms,code,'.$roomId],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'in:'.implode(',', Room::VALID_TYPES)],
            'building' => ['nullable', 'string', 'max:100'],
            'floor' => ['nullable', 'string', 'max:50'],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000'],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['string', 'max:100'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Messages d'erreur personnalisés
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Ce code de salle est déjà utilisé.',
            'type.in' => 'Le type de salle doit être l\'un des suivants : '.implode(', ', Room::VALID_TYPES),
            'capacity.min' => 'La capacité doit être d\'au moins 1.',
        ];
    }
}
