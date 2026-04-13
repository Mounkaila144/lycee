<?php

namespace Modules\Documents\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Documents\Database\Factories\VerificationLogFactory;
use Modules\UsersGuard\Entities\User;

class VerificationLog extends Model
{
    use HasFactory;

    protected static function newFactory(): VerificationLogFactory
    {
        return VerificationLogFactory::new();
    }

    protected $connection = 'tenant';

    protected $table = 'verification_log';

    protected $fillable = [
        'document_id',
        'verified_at',
        'verification_method',
        'ip_address',
        'user_agent',
        'country',
        'city',
        'verified_by',
        'verification_successful',
        'verification_notes',
        'request_data',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'verification_successful' => 'boolean',
            'request_data' => 'array',
        ];
    }

    /**
     * Relations
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Scopes
     */
    public function scopeSuccessful($query)
    {
        return $query->where('verification_successful', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('verification_successful', false);
    }

    public function scopeByMethod($query, string $method)
    {
        return $query->where('verification_method', $method);
    }

    public function scopeFromIp($query, string $ip)
    {
        return $query->where('ip_address', $ip);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('verified_at', today());
    }

    /**
     * Business Logic
     */
    public function isSuccessful(): bool
    {
        return $this->verification_successful;
    }

    public function isFailed(): bool
    {
        return ! $this->verification_successful;
    }

    public function getLocationString(): string
    {
        $parts = array_filter([$this->city, $this->country]);

        return implode(', ', $parts) ?: 'Unknown';
    }
}
