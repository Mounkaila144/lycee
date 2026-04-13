<?php

namespace Modules\Documents\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Documents\Database\Factories\DocumentArchiveFactory;
use Modules\UsersGuard\Entities\User;

class DocumentArchive extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): DocumentArchiveFactory
    {
        return DocumentArchiveFactory::new();
    }

    protected $connection = 'tenant';

    protected $table = 'document_archive';

    protected $fillable = [
        'document_id',
        'archived_at',
        'archive_location',
        'archive_format',
        'checksum',
        'file_size',
        'storage_tier',
        'last_accessed_at',
        'access_count',
        'archived_by',
        'archive_notes',
        'is_encrypted',
        'encryption_method',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'file_size' => 'integer',
            'access_count' => 'integer',
            'is_encrypted' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * Relations
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /**
     * Scopes
     */
    public function scopeInTier($query, string $tier)
    {
        return $query->where('storage_tier', $tier);
    }

    public function scopeEncrypted($query)
    {
        return $query->where('is_encrypted', true);
    }

    public function scopeRecentlyAccessed($query, int $days = 30)
    {
        return $query->where('last_accessed_at', '>=', now()->subDays($days));
    }

    public function scopeStale($query, int $days = 365)
    {
        return $query->where('archived_at', '<=', now()->subDays($days))
            ->where(function ($q) use ($days) {
                $q->whereNull('last_accessed_at')
                    ->orWhere('last_accessed_at', '<=', now()->subDays($days));
            });
    }

    /**
     * Accessors
     */
    public function getFileSizeHumanAttribute(): string
    {
        if (! $this->file_size) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->file_size;
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Business Logic
     */
    public function verifyIntegrity(string $filePath): bool
    {
        $calculatedChecksum = hash_file('sha256', $filePath);

        return hash_equals($this->checksum, $calculatedChecksum);
    }

    public function recordAccess(): bool
    {
        return $this->update([
            'last_accessed_at' => now(),
            'access_count' => $this->access_count + 1,
        ]);
    }

    public function moveToColdStorage(): bool
    {
        return $this->update(['storage_tier' => 'cold']);
    }

    public function moveToHotStorage(): bool
    {
        return $this->update(['storage_tier' => 'hot']);
    }

    public function isStale(int $days = 365): bool
    {
        $staleDate = now()->subDays($days);

        if ($this->last_accessed_at) {
            return $this->last_accessed_at->isBefore($staleDate);
        }

        return $this->archived_at->isBefore($staleDate);
    }

    public function calculateChecksum(string $filePath): string
    {
        return hash_file('sha256', $filePath);
    }
}
