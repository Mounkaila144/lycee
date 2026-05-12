<?php

namespace Modules\Messaging\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Messaging\Entities\Message;

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
