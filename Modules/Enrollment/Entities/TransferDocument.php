<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TransferDocument extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'transfer_documents';

    public const TYPE_TRANSCRIPT = 'transcript';

    public const TYPE_CERTIFICATE = 'certificate';

    public const TYPE_ATTESTATION = 'attestation';

    public const TYPE_SYLLABUS = 'syllabus';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_TRANSCRIPT,
        self::TYPE_CERTIFICATE,
        self::TYPE_ATTESTATION,
        self::TYPE_SYLLABUS,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'transfer_id',
        'type',
        'original_name',
        'path',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * Relations
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * Accessors
     */
    public function getUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return Storage::disk('tenant')->url($this->path);
    }

    public function getSizeFormattedAttribute(): string
    {
        $bytes = $this->size;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' bytes';
    }

    /**
     * Get type label
     */
    public function getTypeLabel(): string
    {
        $labels = [
            self::TYPE_TRANSCRIPT => 'Relevé de notes',
            self::TYPE_CERTIFICATE => 'Certificat',
            self::TYPE_ATTESTATION => 'Attestation',
            self::TYPE_SYLLABUS => 'Syllabus',
            self::TYPE_OTHER => 'Autre',
        ];

        return $labels[$this->type] ?? $this->type;
    }

    /**
     * Check if document is a PDF
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}
