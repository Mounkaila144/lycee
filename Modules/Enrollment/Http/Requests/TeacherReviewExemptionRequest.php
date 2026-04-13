<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeacherReviewExemptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'opinion' => ['required', 'string', 'min:20', 'max:2000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'opinion.required' => 'L\'avis est obligatoire.',
            'opinion.min' => 'L\'avis doit contenir au moins 20 caractères.',
        ];
    }
}
