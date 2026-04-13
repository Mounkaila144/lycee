<?php

namespace Modules\Documents\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Modules\Documents\Database\Factories\ElectronicSignatureFactory;
use Modules\UsersGuard\Entities\User;

class ElectronicSignature extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): ElectronicSignatureFactory
    {
        return ElectronicSignatureFactory::new();
    }

    protected $connection = 'tenant';

    protected $table = 'electronic_signatures';

    protected $fillable = [
        'document_id',
        'signer_name',
        'signer_title',
        'signer_role',
        'signature_date',
        'signature_image_path',
        'certificate_path',
        'signature_hash',
        'is_valid',
        'expires_at',
        'signed_by',
        'signature_metadata',
    ];

    protected function casts(): array
    {
        return [
            'signature_date' => 'datetime',
            'is_valid' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    /**
     * Scopes
     */
    public function scopeValid($query)
    {
        return $query->where('is_valid', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    public function scopeInvalid($query)
    {
        return $query->where('is_valid', false);
    }

    /**
     * Accessors
     */
    public function getSignatureImageUrlAttribute(): ?string
    {
        if (! $this->signature_image_path) {
            return null;
        }

        return Storage::disk('tenant')->url($this->signature_image_path);
    }

    public function getCertificateUrlAttribute(): ?string
    {
        if (! $this->certificate_path) {
            return null;
        }

        return Storage::disk('tenant')->url($this->certificate_path);
    }

    /**
     * Business Logic
     */
    public function isValid(): bool
    {
        if (! $this->is_valid) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function invalidate(): bool
    {
        return $this->update(['is_valid' => false]);
    }

    public function verifyHash(string $documentContent): bool
    {
        $calculatedHash = hash('sha256', $documentContent.$this->signature_date->timestamp);

        return hash_equals($this->signature_hash, $calculatedHash);
    }

    public function generateHash(string $documentContent): string
    {
        return hash('sha256', $documentContent.$this->signature_date->timestamp);
    }
}
