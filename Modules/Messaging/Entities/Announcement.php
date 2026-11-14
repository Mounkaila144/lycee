<?php

namespace Modules\Messaging\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Messaging\Database\Factories\AnnouncementFactory;
use Modules\StructureAcademique\Entities\Classe;
use Modules\UsersGuard\Entities\TenantUser;

/**
 * Annonce de l'établissement, ciblée par audience (et éventuellement par classe).
 */
class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_PARENTS = 'parents';

    public const AUDIENCE_STUDENTS = 'students';

    public const AUDIENCE_TEACHERS = 'teachers';

    public const AUDIENCE_STAFF = 'staff';

    /**
     * @var array<string, string>
     */
    public const AUDIENCE_LABELS = [
        self::AUDIENCE_ALL => 'Tout le monde',
        self::AUDIENCE_PARENTS => 'Parents',
        self::AUDIENCE_STUDENTS => 'Élèves',
        self::AUDIENCE_TEACHERS => 'Professeurs',
        self::AUDIENCE_STAFF => 'Personnel administratif',
    ];

    protected $connection = 'tenant';

    protected $table = 'announcements';

    protected $fillable = [
        'title',
        'body',
        'audience',
        'classe_id',
        'published_at',
        'expires_at',
        'author_id',
        'is_pinned',
    ];

    protected static function newFactory(): AnnouncementFactory
    {
        return AnnouncementFactory::new();
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_pinned' => 'boolean',
        ];
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'author_id');
    }

    /**
     * Annonces publiées (date de publication passée) et non expirées.
     */
    public function scopeCurrentlyVisible(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn (Builder $expiry) => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lessThanOrEqualTo(now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lessThanOrEqualTo(now());
    }
}
