<?php

namespace Modules\Documents\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Modules\Documents\Database\Factories\DocumentFactory;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): DocumentFactory
    {
        return DocumentFactory::new();
    }

    protected $connection = 'tenant';

    protected $table = 'documents';

    protected $fillable = [
        'student_id',
        'document_type',
        'template_id',
        'document_number',
        'issue_date',
        'academic_year_id',
        'semester_id',
        'programme_id',
        'pdf_path',
        'verification_code',
        'qr_code_path',
        'status',
        'metadata',
        'issued_by',
        'replaced_by_document_id',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'metadata' => 'array',
        ];
    }

    /**
     * Relations
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function replacedByDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'replaced_by_document_id');
    }

    public function verificationLogs(): HasMany
    {
        return $this->hasMany(VerificationLog::class);
    }

    public function electronicSignatures(): HasMany
    {
        return $this->hasMany(ElectronicSignature::class);
    }

    public function archive(): HasOne
    {
        return $this->hasOne(DocumentArchive::class);
    }

    public function diplomaRegister(): HasOne
    {
        return $this->hasOne(DiplomaRegister::class);
    }

    /**
     * Scopes
     */
    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeInAcademicYear($query, int $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    /**
     * Accessors
     */
    public function getPdfUrlAttribute(): ?string
    {
        if (! $this->pdf_path) {
            return null;
        }

        return Storage::disk('tenant')->url($this->pdf_path);
    }

    public function getQrCodeUrlAttribute(): ?string
    {
        if (! $this->qr_code_path) {
            return null;
        }

        return Storage::disk('tenant')->url($this->qr_code_path);
    }

    /**
     * Business Logic
     */
    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isReplaced(): bool
    {
        return $this->status === 'replaced';
    }

    public function cancel(string $reason): bool
    {
        return $this->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
        ]);
    }

    public function markAsReplaced(int $replacementDocumentId): bool
    {
        return $this->update([
            'status' => 'replaced',
            'replaced_by_document_id' => $replacementDocumentId,
        ]);
    }

    public function verify(): bool
    {
        return $this->status === 'issued' && ! $this->deleted_at;
    }

    public function logVerification(array $data): VerificationLog
    {
        return $this->verificationLogs()->create($data);
    }

    public function getVerificationCount(): int
    {
        return $this->verificationLogs()->count();
    }

    public function getLastVerification(): ?VerificationLog
    {
        return $this->verificationLogs()->latest('verified_at')->first();
    }
}
