<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Database\Factories\StudentCardFactory;
use Modules\StructureAcademique\Entities\AcademicYear;

class StudentCard extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'student_cards';

    public const STATUS_ACTIVE = 'Active';

    public const STATUS_EXPIRED = 'Expired';

    public const STATUS_SUSPENDED = 'Suspended';

    public const STATUS_REVOKED = 'Revoked';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_EXPIRED,
        self::STATUS_SUSPENDED,
        self::STATUS_REVOKED,
    ];

    public const PRINT_STATUS_PENDING = 'Pending';

    public const PRINT_STATUS_PRINTED = 'Printed';

    public const PRINT_STATUS_DELIVERED = 'Delivered';

    public const PRINT_STATUSES = [
        self::PRINT_STATUS_PENDING,
        self::PRINT_STATUS_PRINTED,
        self::PRINT_STATUS_DELIVERED,
    ];

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'card_number',
        'qr_code_data',
        'qr_signature',
        'pdf_path',
        'status',
        'issued_at',
        'valid_until',
        'is_duplicate',
        'original_card_id',
        'print_status',
        'printed_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'valid_until' => 'date',
            'is_duplicate' => 'boolean',
            'printed_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    protected static function newFactory(): StudentCardFactory
    {
        return StudentCardFactory::new();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function originalCard(): BelongsTo
    {
        return $this->belongsTo(StudentCard::class, 'original_card_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForAcademicYear($query, int $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopePendingPrint($query)
    {
        return $query->where('print_status', self::PRINT_STATUS_PENDING);
    }

    public function scopeOriginals($query)
    {
        return $query->where('is_duplicate', false);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && now()->lt($this->valid_until);
    }

    public function isExpired(): bool
    {
        return now()->gte($this->valid_until);
    }

    /**
     * Check if the card is currently valid (active and not expired)
     */
    public function isValid(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired();
    }

    /**
     * Get the number of days until the card expires
     */
    public function getDaysUntilExpiry(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return now()->diffInDays($this->valid_until);
    }

    public function getQrDataArray(): array
    {
        return json_decode($this->qr_code_data, true) ?? [];
    }
}
