<?php

namespace Modules\Messaging\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Messaging\Entities\Announcement;

/**
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $author = $this->relationLoaded('author') ? $this->author : null;
        $authorName = $author !== null ? trim(($author->firstname ?? '').' '.($author->lastname ?? '')) : '';

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'audience' => $this->audience,
            'audience_label' => Announcement::AUDIENCE_LABELS[$this->audience] ?? $this->audience,
            'classe_id' => $this->classe_id,
            'classe' => $this->relationLoaded('classe') && $this->classe !== null
                ? ['id' => $this->classe->id, 'name' => $this->classe->name]
                : null,
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_pinned' => (bool) $this->is_pinned,
            'is_published' => $this->isPublished(),
            'is_expired' => $this->isExpired(),
            'author' => $author !== null
                ? ['id' => $author->id, 'full_name' => $authorName !== '' ? $authorName : (string) $author->username]
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
