<?php

namespace Modules\Enrollment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReenrollmentIntentionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'campaign_id' => ['required', 'integer', Rule::exists('tenant.reenrollment_campaigns', 'id')->whereNull('deleted_at')],
            'wants_to_reenroll' => ['required', 'boolean'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'campaign_id.required' => 'La campagne est obligatoire.',
            'campaign_id.exists' => 'La campagne est introuvable.',
            'wants_to_reenroll.required' => 'Indiquez si vous souhaitez vous réinscrire.',
            'wants_to_reenroll.boolean' => 'La réponse doit être oui ou non.',
            'comment.max' => 'Le commentaire ne doit pas dépasser 500 caractères.',
        ];
    }
}
