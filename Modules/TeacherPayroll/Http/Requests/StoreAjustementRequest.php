<?php

namespace Modules\TeacherPayroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation partagée pour les primes et retenues.
 */
class StoreAjustementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'professeur_id' => ['required', 'integer'],
            'periode_paie_id' => ['nullable', 'integer'],
            'type_prime' => ['nullable', 'string', 'max:255'],
            'type_retenue' => ['nullable', 'string', 'max:255'],
            'libelle' => ['required', 'string', 'max:255'],
            'montant' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ];
    }
}
