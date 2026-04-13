<?php

namespace Modules\Documents\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Modules\Documents\Database\Factories\StudentCardFactory;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\UsersGuard\Entities\User;

class StudentCard extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): StudentCardFactory
    {
        return StudentCardFactory::new();
    }

    protected $connection = 'tenant';

    protected $table = 'student_cards';

    protected $fillable = [
        'student_id',
        'card_number',
        'card_type',
        'issue_date',
        'expiry_date',
        'academic_year_id',
        'photo_path',
        'qr_code',
        'qr_code_path',
        'barcode',
        'barcode_path',
        'status',
        'access_permissions',
        'is_printed',
        'printed_at',
        'printed_by',
        'replaced_by_card_id',
        'replacement_reason',
        'document_id',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'access_permissions' => 'array',
            'is_printed' => 'boolean',
            'printed_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    public function replacedByCard(): BelongsTo
    {
        return $this->belongsTo(StudentCard::class, 'replaced_by_card_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('expiry_date', '>=', today());
    }

    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'expired')
                ->orWhere('expiry_date', '<', today());
        });
    }

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopePrinted($query)
    {
        return $query->where('is_printed', true);
    }

    public function scopePendingPrint($query)
    {
        return $query->where('is_printed', false)
            ->where('status', 'active');
    }

    /**
     * Accessors
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return Storage::disk('tenant')->url($this->photo_path);
    }

    public function getQrCodeUrlAttribute(): ?string
    {
        if (! $this->qr_code_path) {
            return null;
        }

        return Storage::disk('tenant')->url($this->qr_code_path);
    }

    public function getBarcodeUrlAttribute(): ?string
    {
        if (! $this->barcode_path) {
            return null;
        }

        return Storage::disk('tenant')->url($this->barcode_path);
    }

    /**
     * Business Logic
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function isLost(): bool
    {
        return $this->status === 'lost';
    }

    public function isStolen(): bool
    {
        return $this->status === 'stolen';
    }

    public function markAsLost(string $reason): bool
    {
        return $this->update([
            'status' => 'lost',
            'replacement_reason' => $reason,
        ]);
    }

    public function markAsStolen(string $reason): bool
    {
        return $this->update([
            'status' => 'stolen',
            'replacement_reason' => $reason,
        ]);
    }

    public function markAsPrinted(int $printedBy): bool
    {
        return $this->update([
            'is_printed' => true,
            'printed_at' => now(),
            'printed_by' => $printedBy,
        ]);
    }

    public function markAsReplaced(int $replacementCardId): bool
    {
        return $this->update([
            'status' => 'replaced',
            'replaced_by_card_id' => $replacementCardId,
        ]);
    }

    public function suspend(): bool
    {
        return $this->update(['status' => 'suspended']);
    }

    public function activate(): bool
    {
        return $this->update(['status' => 'active']);
    }

    public function hasAccessPermission(string $permission): bool
    {
        if (! $this->access_permissions) {
            return false;
        }

        return in_array($permission, $this->access_permissions);
    }

    public function grantAccessPermission(string $permission): bool
    {
        $permissions = $this->access_permissions ?? [];

        if (! in_array($permission, $permissions)) {
            $permissions[] = $permission;

            return $this->update(['access_permissions' => $permissions]);
        }

        return true;
    }

    public function revokeAccessPermission(string $permission): bool
    {
        $permissions = $this->access_permissions ?? [];
        $permissions = array_diff($permissions, [$permission]);

        return $this->update(['access_permissions' => array_values($permissions)]);
    }
}
