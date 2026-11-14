<?php

namespace Modules\Messaging\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Messaging\Entities\Message;
use Modules\Messaging\Http\Requests\StoreMessageRequest;
use Modules\UsersGuard\Entities\TenantUser;

/**
 * Story Parent 07 — Messages Parent ↔ Enseignants.
 *
 * Ownership : un utilisateur ne voit que les messages où il est sender ou recipient.
 * Pas de query param `user_id=X` — toujours auth()->user()->id.
 */
class MessagingController extends Controller
{
    private const PARTICIPANT_RELATIONS = [
        'sender:id,username,firstname,lastname',
        'recipient:id,username,firstname,lastname',
    ];

    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $messages = Message::query()
            ->where(fn ($q) => $q->where('sender_id', $userId)->orWhere('recipient_id', $userId))
            ->with(self::PARTICIPANT_RELATIONS)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (Message $message): array => $this->presentMessage($message, $userId));

        $unreadCount = Message::query()
            ->where('recipient_id', $userId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => $messages,
            'meta' => ['unread_count' => $unreadCount],
        ]);
    }

    public function store(StoreMessageRequest $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $message = Message::create([
            ...$request->validated(),
            'sender_id' => $userId,
        ]);

        $message->load(self::PARTICIPANT_RELATIONS);

        return response()->json([
            'message' => 'Message envoyé.',
            'data' => $this->presentMessage($message, $userId),
        ], 201);
    }

    /**
     * Liste les destinataires possibles pour l'utilisateur courant.
     *
     * Règles (Story Parent 07) :
     *  - Parent       → peut messager Professeur, Administrator, Manager
     *  - Professeur   → peut messager Parent, Administrator, Manager
     *  - Administrator / Manager → peuvent messager Parent, Professeur, Administrator, Manager (sauf soi-même)
     *
     * Query params :
     *  - q (optionnel) : recherche sur firstname/lastname/username/email
     *  - limit (optionnel, défaut 25, max 50)
     *
     * Chaque utilisateur n'apparaît qu'une fois, avec ses rôles autorisés (`roles`) ;
     * `meta.allowed_roles` permet au front d'adapter le texte d'aide.
     */
    public function recipients(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $currentUser = $request->user();
        $allowedRoles = $this->allowedRecipientRoles($currentUser);

        $query = TenantUser::query()
            ->where('id', '!=', $currentUser->id)
            ->where('is_active', true)
            ->whereHas('roles', function ($q) use ($allowedRoles) {
                $q->whereIn('name', $allowedRoles);
            })
            ->with(['roles:id,name']);

        if (! empty($validated['q'])) {
            $term = '%'.$validated['q'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('firstname', 'like', $term)
                    ->orWhere('lastname', 'like', $term)
                    ->orWhere('username', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        $limit = $validated['limit'] ?? 25;

        $users = $query
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->limit($limit)
            ->get(['id', 'username', 'firstname', 'lastname', 'email']);

        $data = $users
            ->unique('id')
            ->values()
            ->map(function (TenantUser $user) use ($allowedRoles): array {
                $fullName = trim(($user->firstname ?? '').' '.($user->lastname ?? ''));
                $roles = $user->roles->pluck('name')
                    ->filter(fn (string $role): bool => in_array($role, $allowedRoles, true))
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'full_name' => $fullName !== '' ? $fullName : $user->username,
                    'email' => $user->email,
                    'role' => $roles[0] ?? null,
                    'roles' => $roles,
                ];
            });

        return response()->json([
            'data' => $data,
            'meta' => ['allowed_roles' => $allowedRoles],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function allowedRecipientRoles(TenantUser $user): array
    {
        return StoreMessageRequest::allowedRecipientRoles($user);
    }

    /**
     * Pas de route model binding : le binding s'exécute avant l'initialisation
     * de la connexion tenant (middleware `tenant`) et provoquait une erreur 500.
     */
    public function show(Request $request, int $message): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $found = Message::query()->with(self::PARTICIPANT_RELATIONS)->find($message);

        if ($found === null) {
            return response()->json(['message' => 'Message introuvable.'], 404);
        }

        if ((int) $found->sender_id !== $userId && (int) $found->recipient_id !== $userId) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ((int) $found->recipient_id === $userId && ! $found->read_at) {
            $found->update(['read_at' => now()]);
        }

        return response()->json(['data' => $this->presentMessage($found, $userId)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMessage(Message $message, int $currentUserId): array
    {
        return [
            ...$message->toArray(),
            'direction' => (int) $message->sender_id === $currentUserId ? 'sent' : 'received',
            'sender' => $this->presentParticipant($message->sender),
            'recipient' => $this->presentParticipant($message->recipient),
        ];
    }

    /**
     * @return array{id: int, username: string|null, full_name: string}|null
     */
    private function presentParticipant(?TenantUser $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $fullName = trim(($user->firstname ?? '').' '.($user->lastname ?? ''));

        return [
            'id' => $user->id,
            'username' => $user->username,
            'full_name' => $fullName !== '' ? $fullName : (string) $user->username,
        ];
    }
}
