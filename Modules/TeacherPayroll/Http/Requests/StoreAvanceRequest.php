<?php

namespace Modules\TeacherPayroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAvanceRequest extends FormRequest
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
            'montant' => ['required', 'numeric', 'min:0'],
            'date_avance' => ['required', 'date'],
            'motif' => ['nullable', 'string', 'max:255'],
            'periode_deduction_id' => ['nullable', 'integer'],
        ];
    }
}
