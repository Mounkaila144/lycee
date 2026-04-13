<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qr_data' => ['required', 'json'],
            'signature' => ['required', 'string', 'size:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'qr_data.required' => 'Les données QR sont obligatoires.',
            'qr_data.json' => 'Les données QR doivent être au format JSON.',
            'signature.required' => 'La signature est obligatoire.',
            'signature.size' => 'La signature doit faire 64 caractères.',
        ];
    }
}
