<?php

namespace Modules\TeacherPayroll\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnregistrerPaiementRequest extends FormRequest
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
            'montant' => ['required', 'numeric', 'min:0.01'],
            'date_paiement' => ['nullable', 'date'],
            'mode_reglement' => ['nullable', 'in:ESPECES,MOBILE_MONEY,VIREMENT_BANCAIRE,CHEQUE,AUTRE'],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ];
    }
}
