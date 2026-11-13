<?php

namespace Modules\Messaging\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Modules\UsersGuard\Entities\TenantUser;

class StoreMessageRequest extends FormRequest
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
            'recipient_id' => [
                'required',
                'integer',
                'exists:tenant.users,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $this->validateRecipientIsAllowed((int) $value, $fail);
                },
            ],
            'thread_id' => ['nullable', 'integer', 'exists:tenant.messages,id'],
            'student_context_id' => ['nullable', 'integer', 'exists:tenant.students,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipient_id.required' => 'Le destinataire est obligatoire.',
            'recipient_id.exists' => 'Le destinataire est introuvable.',
            'thread_id.exists' => 'La conversation est introuvable.',
            'student_context_id.exists' => 'L\'élève concerné est introuvable.',
            'subject.required' => 'Le sujet est obligatoire.',
            'subject.max' => 'Le sujet ne doit pas dépasser 255 caractères.',
            'body.required' => 'Le message est obligatoire.',
            'body.max' => 'Le message ne doit pas dépasser 5000 caractères.',
        ];
    }

    /**
     * Rôles que l'utilisateur courant peut contacter (Story Parent 07).
     *
     * @return array<int, string>
     */
    public static function allowedRecipientRoles(TenantUser $user): array
    {
        if ($user->hasRole('Administrator')) {
            return ['Parent', 'Professeur', 'Administrator', 'Manager'];
        }

        if ($user->hasRole('Professeur')) {
            return ['Parent', 'Administrator'];
        }

        if ($user->hasRole('Parent')) {
            return ['Professeur', 'Administrator'];
        }

        return [];
    }

    private function validateRecipientIsAllowed(int $recipientId, Closure $fail): void
    {
        $sender = $this->user();

        if ($recipientId === (int) $sender->id) {
            $fail('Vous ne pouvez pas vous envoyer un message à vous-même.');

            return;
        }

        $isAllowed = TenantUser::query()
            ->whereKey($recipientId)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', self::allowedRecipientRoles($sender)))
            ->exists();

        if (! $isAllowed) {
            $fail('Vous n\'êtes pas autorisé à écrire à ce destinataire.');
        }
    }
}
