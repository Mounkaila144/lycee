<?php

namespace Modules\Messaging\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Messaging\Entities\Announcement;
use Modules\Messaging\Http\Requests\StoreAnnouncementRequest;
use Modules\Messaging\Http\Requests\UpdateAnnouncementRequest;
use Modules\Messaging\Http\Resources\AnnouncementResource;
use Modules\Messaging\Services\AnnouncementAudienceService;

/**
 * Annonces de l'établissement.
 *
 *  - CRUD : Administrator | Manager ;
 *  - feed : lecture filtrée par audience pour chaque rôle (cf. AnnouncementAudienceService).
 *
 * Pas de route model binding (le binding s'exécuterait avant l'initialisation du tenant).
 * Tant que la migration `announcements` n'est pas jouée, les lectures renvoient une liste vide.
 */
class AnnouncementController extends Controller
{
    private const TABLE_MISSING_MESSAGE = 'Le module Annonces n\'est pas encore installé sur cet établissement.';

    public function __construct(private AnnouncementAudienceService $audienceService) {}

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $validated = $request->validate([
            'audience' => ['nullable', 'string', Rule::in(array_keys(Announcement::AUDIENCE_LABELS))],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (! $this->audienceService->tableExists()) {
            return $this->emptyList();
        }

        $announcements = Announcement::query()
            ->with(['classe:id,name', 'author:id,username,firstname,lastname'])
            ->when($validated['audience'] ?? null, fn ($query, string $audience) => $query->where('audience', $audience))
            ->when($validated['search'] ?? null, fn ($query, string $search) => $query->where(
                fn ($searchQuery) => $searchQuery->where('title', 'like', "%{$search}%")->orWhere('body', 'like', "%{$search}%")
            ))
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 20);

        return AnnouncementResource::collection($announcements);
    }

    /**
     * Dernières annonces visibles par l'utilisateur connecté (tous rôles).
     */
    public function feed(Request $request): JsonResponse
    {
        $validated = $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:50']]);

        $announcements = $this->audienceService->feedFor($request->user(), $validated['limit'] ?? 20);

        return response()->json(['data' => AnnouncementResource::collection($announcements)->resolve()]);
    }

    /**
     * Aperçu de l'audience : libellé + nombre de comptes ciblés.
     */
    public function audiencePreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audience' => ['required', 'string', Rule::in(array_keys(Announcement::AUDIENCE_LABELS))],
            'classe_id' => ['nullable', 'integer'],
        ]);

        return response()->json([
            'data' => $this->audienceService->preview(
                $validated['audience'],
                isset($validated['classe_id']) ? (int) $validated['classe_id'] : null
            ),
        ]);
    }

    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        if (! $this->audienceService->tableExists()) {
            return response()->json(['message' => self::TABLE_MISSING_MESSAGE], 503);
        }

        $announcement = Announcement::query()->create([
            ...$request->validated(),
            'is_pinned' => $request->boolean('is_pinned'),
            'author_id' => $request->user()->id,
        ]);

        $announcement->load(['classe:id,name', 'author:id,username,firstname,lastname']);

        return response()->json([
            'message' => 'Annonce créée.',
            'data' => (new AnnouncementResource($announcement))->resolve(),
        ], 201);
    }

    public function show(int $announcement): JsonResponse
    {
        $found = $this->findOrNull($announcement);

        if ($found === null) {
            return response()->json(['message' => 'Annonce introuvable.'], 404);
        }

        return response()->json(['data' => (new AnnouncementResource($found))->resolve()]);
    }

    public function update(UpdateAnnouncementRequest $request, int $announcement): JsonResponse
    {
        $found = $this->findOrNull($announcement);

        if ($found === null) {
            return response()->json(['message' => 'Annonce introuvable.'], 404);
        }

        $found->update([
            ...$request->validated(),
            'classe_id' => $request->validated('classe_id'),
            'expires_at' => $request->validated('expires_at'),
            'is_pinned' => $request->boolean('is_pinned'),
        ]);

        return response()->json([
            'message' => 'Annonce mise à jour.',
            'data' => (new AnnouncementResource($found->fresh(['classe:id,name', 'author:id,username,firstname,lastname'])))->resolve(),
        ]);
    }

    public function destroy(int $announcement): JsonResponse
    {
        $found = $this->findOrNull($announcement);

        if ($found === null) {
            return response()->json(['message' => 'Annonce introuvable.'], 404);
        }

        $found->delete();

        return response()->json(['message' => 'Annonce supprimée.']);
    }

    private function findOrNull(int $announcementId): ?Announcement
    {
        if (! $this->audienceService->tableExists()) {
            return null;
        }

        return Announcement::query()
            ->with(['classe:id,name', 'author:id,username,firstname,lastname'])
            ->find($announcementId);
    }

    private function emptyList(): JsonResponse
    {
        return response()->json([
            'data' => [],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0],
        ]);
    }
}
