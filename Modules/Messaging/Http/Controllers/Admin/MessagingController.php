<?php

namespace Modules\Messaging\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Messaging\Entities\Message;
use Modules\UsersGuard\Entities\TenantUser;

/**
 * Story Parent 07 — Messages Parent ↔ Enseignants.
 *
 * Ownership : un utilisateur ne voit que les messages où il est sender ou recipient.
 * Pas de query param `user_id=X` — toujours auth()->user()->id.
 */
class MessagingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $messages = Message::query()
            ->where(fn ($q) => $q->where('sender_id', $userId)->orWhere('recipient_id', $userId))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json(['data' => $messages]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:tenant.users,id'],
            'thread_id' => ['nullable', 'integer'],
            'student_context_id' => ['nullable', 'integer'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = Message::create([
            ...$validated,
            'sender_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Message envoyé.',
            'data' => $message,
        ], 201);
    }

    /**
     * Liste les destinataires possibles pour l'utilisateur courant.
     *
     * Règles (Story Parent 07) :
     *  - Parent       → peut messager Professeur, Administrator
     *  - Professeur   → peut messager Parent, Administrator
     *  - Administrator → peut messager tout le monde sauf lui-même
     *
     * Query params :
     *  - q (optionnel) : recherche sur firstname/lastname/username/email
     *  - limit (optionnel, défaut 25, max 50)
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

        $data = $users->map(function (TenantUser $user) {
            $fullName = trim(($user->firstname ?? '').' '.($user->lastname ?? ''));

            return [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $fullName !== '' ? $fullName : $user->username,
                'email' => $user->email,
                'role' => $user->roles->pluck('name')->first(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * @return array<int, string>
     */
    private function allowedRecipientRoles(TenantUser $user): array
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

    public function show(Request $request, Message $message): JsonResponse
    {
        $userId = $request->user()->id;

        if ($message->sender_id !== $userId && $message->recipient_id !== $userId) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Marquer comme lu si destinataire
        if ($message->recipient_id === $userId && ! $message->read_at) {
            $message->update(['read_at' => now()]);
        }

        return response()->json(['data' => $message]);
    }
}
