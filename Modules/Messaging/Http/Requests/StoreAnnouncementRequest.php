<?php

namespace Modules\Messaging\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Messaging\Entities\Announcement;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sans date de publication, l'annonce est publiée immédiatement.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('published_at')) {
            $this->merge(['published_at' => now()->toDateTimeString()]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'audience' => ['required', 'string', Rule::in(array_keys(Announcement::AUDIENCE_LABELS))],
            'classe_id' => ['nullable', 'integer', 'exists:tenant.classes,id', 'prohibited_if:audience,'.Announcement::AUDIENCE_STAFF],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
            'is_pinned' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Le titre est obligatoire.',
            'title.max' => 'Le titre ne doit pas dépasser 255 caractères.',
            'body.required' => 'Le contenu est obligatoire.',
            'body.max' => 'Le contenu ne doit pas dépasser 10 000 caractères.',
            'audience.required' => 'L\'audience est obligatoire.',
            'audience.in' => 'L\'audience doit être : tous, parents, élèves, professeurs ou personnel.',
            'classe_id.exists' => 'La classe est introuvable.',
            'classe_id.prohibited_if' => 'Une annonce destinée au personnel ne peut pas cibler une classe.',
            'published_at.date' => 'La date de publication est invalide.',
            'expires_at.date' => 'La date d\'expiration est invalide.',
            'expires_at.after' => 'La date d\'expiration doit être postérieure à la date de publication.',
        ];
    }
}
