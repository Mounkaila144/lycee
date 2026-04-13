<?php

namespace Modules\Documents\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Documents\Database\Factories\DiplomaRegisterFactory;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;
use Modules\UsersGuard\Entities\User;

class DiplomaRegister extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): DiplomaRegisterFactory
    {
        return DiplomaRegisterFactory::new();
    }

    protected $connection = 'tenant';

    protected $table = 'diploma_register';

    protected $fillable = [
        'student_id',
        'programme_id',
        'diploma_number',
        'register_number',
        'issue_date',
        'graduation_date',
        'academic_year_id',
        'honors',
        'final_gpa',
        'diploma_type',
        'specialization',
        'document_id',
        'supplement_generated',
        'supplement_document_id',
        'is_duplicate',
        'original_diploma_id',
        'duplicate_reason',
        'delivered_by',
        'delivered_at',
        'recipient_name',
        'recipient_id_type',
        'recipient_id_number',
        'delivery_notes',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'graduation_date' => 'date',
            'final_gpa' => 'decimal:2',
            'supplement_generated' => 'boolean',
            'is_duplicate' => 'boolean',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function supplementDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'supplement_document_id');
    }

    public function originalDiploma(): BelongsTo
    {
        return $this->belongsTo(DiplomaRegister::class, 'original_diploma_id');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /**
     * Scopes
     */
    public function scopeDuplicates($query)
    {
        return $query->where('is_duplicate', true);
    }

    public function scopeOriginals($query)
    {
        return $query->where('is_duplicate', false);
    }

    public function scopeWithHonors($query)
    {
        return $query->whereNotNull('honors')->where('honors', '!=', 'none');
    }

    public function scopeDelivered($query)
    {
        return $query->whereNotNull('delivered_at');
    }

    public function scopePendingDelivery($query)
    {
        return $query->whereNull('delivered_at');
    }

    /**
     * Accessors
     */
    public function getHonorsLabelAttribute(): string
    {
        return match ($this->honors) {
            'passable' => 'Passable',
            'assez_bien' => 'Assez Bien',
            'bien' => 'Bien',
            'tres_bien' => 'Très Bien',
            'excellent' => 'Excellent',
            default => 'Sans mention',
        };
    }

    /**
     * Business Logic
     */
    public function isDuplicate(): bool
    {
        return $this->is_duplicate;
    }

    public function hasHonors(): bool
    {
        return $this->honors && $this->honors !== 'none';
    }

    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }

    public function markAsDelivered(int $deliveredBy, string $recipientName, string $recipientIdType, string $recipientIdNumber, ?string $notes = null): bool
    {
        return $this->update([
            'delivered_by' => $deliveredBy,
            'delivered_at' => now(),
            'recipient_name' => $recipientName,
            'recipient_id_type' => $recipientIdType,
            'recipient_id_number' => $recipientIdNumber,
            'delivery_notes' => $notes,
        ]);
    }

    public function generateSupplement(): bool
    {
        return $this->update(['supplement_generated' => true]);
    }

    public function calculateHonors(float $gpa): string
    {
        return match (true) {
            $gpa >= 16 => 'excellent',
            $gpa >= 14 => 'tres_bien',
            $gpa >= 12 => 'bien',
            $gpa >= 10 => 'assez_bien',
            default => 'passable',
        };
    }
}
