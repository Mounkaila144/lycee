<?php

namespace Modules\Enrollment\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Modules\Enrollment\Database\Factories\StudentDocumentFactory;

class StudentDocument extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): StudentDocumentFactory
    {
        return StudentDocumentFactory::new();
    }

    protected $connection = 'tenant';

    protected $table = 'student_documents';

    protected $fillable = [
        'student_id',
        'type',
        'filename',
        'original_filename',
        'file_path',
        'mime_type',
        'file_size',
        'description',
        'is_validated',
        'validated_by',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_validated' => 'boolean',
            'validated_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Scopes
     */
    public function scopeValidated($query)
    {
        return $query->where('is_validated', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_validated', false);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Accessors
     */
    public function getFileUrlAttribute(): string
    {
        return Storage::disk('tenant')->url($this->file_path);
    }

    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        } elseif ($bytes > 1) {
            return $bytes.' bytes';
        } elseif ($bytes == 1) {
            return $bytes.' byte';
        } else {
            return '0 bytes';
        }
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'certificat_naissance' => 'Certificat de naissance',
            'releve_baccalaureat' => 'Relevé de notes baccalauréat',
            'photo_identite' => 'Photo d\'identité',
            'cni_passeport' => 'CNI/Passeport',
            'autre' => 'Autre document',
            default => $this->type,
        };
    }

    /**
     * Business Logic Methods
     */
    public function validate(User $user): void
    {
        $this->update([
            'is_validated' => true,
            'validated_by' => $user->id,
            'validated_at' => now(),
        ]);
    }

    public function invalidate(): void
    {
        $this->update([
            'is_validated' => false,
            'validated_by' => null,
            'validated_at' => null,
        ]);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Delete file from storage when model is deleted
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($document) {
            if (Storage::disk('tenant')->exists($document->file_path)) {
                Storage::disk('tenant')->delete($document->file_path);
            }
        });
    }
}
