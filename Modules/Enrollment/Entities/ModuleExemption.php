<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Module;
use Modules\UsersGuard\Entities\User;

class ModuleExemption extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'module_exemptions';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public const TYPE_FULL = 'Full';

    public const TYPE_PARTIAL = 'Partial';

    public const TYPE_EXEMPTION = 'Exemption';

    public const TYPES = [
        self::TYPE_FULL,
        self::TYPE_PARTIAL,
        self::TYPE_EXEMPTION,
    ];

    public const REASON_VAE = 'VAE';

    public const REASON_PRIOR_TRAINING = 'Prior_Training';

    public const REASON_PROFESSIONAL_CERTIFICATION = 'Professional_Certification';

    public const REASON_SPECIAL_SITUATION = 'Special_Situation';

    public const REASON_DOUBLE_DEGREE = 'Double_Degree';

    public const REASON_OTHER = 'Other';

    public const REASON_CATEGORIES = [
        self::REASON_VAE,
        self::REASON_PRIOR_TRAINING,
        self::REASON_PROFESSIONAL_CERTIFICATION,
        self::REASON_SPECIAL_SITUATION,
        self::REASON_DOUBLE_DEGREE,
        self::REASON_OTHER,
    ];

    public const STATUS_PENDING = 'Pending';

    public const STATUS_UNDER_REVIEW = 'Under_Review';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_PARTIALLY_APPROVED = 'Partially_Approved';

    public const STATUS_REJECTED = 'Rejected';

    public const STATUS_REVOKED = 'Revoked';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_PARTIALLY_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_REVOKED,
    ];

    protected $fillable = [
        'exemption_number',
        'student_id',
        'module_id',
        'academic_year_id',
        'exemption_type',
        'reason_category',
        'reason_details',
        'uploaded_documents',
        'status',
        'reviewed_by_teacher',
        'teacher_opinion',
        'teacher_reviewed_at',
        'validated_by',
        'validation_notes',
        'validated_at',
        'rejection_reason',
        'grants_ects',
        'ects_granted',
        'grade_granted',
        'certificate_path',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_documents' => 'array',
            'teacher_reviewed_at' => 'datetime',
            'validated_at' => 'datetime',
            'revoked_at' => 'datetime',
            'grants_ects' => 'boolean',
            'ects_granted' => 'integer',
            'grade_granted' => 'decimal:2',
        ];
    }

    /**
     * Relations
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function teacherReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_teacher');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function revokedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', self::STATUS_UNDER_REVIEW);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePartiallyApproved($query)
    {
        return $query->where('status', self::STATUS_PARTIALLY_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeRevoked($query)
    {
        return $query->where('status', self::STATUS_REVOKED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_APPROVED,
            self::STATUS_PARTIALLY_APPROVED,
        ]);
    }

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeForAcademicYear($query, int $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeByReason($query, string $reason)
    {
        return $query->where('reason_category', $reason);
    }

    /**
     * Business Logic
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isUnderReview(): bool
    {
        return $this->status === self::STATUS_UNDER_REVIEW;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPartiallyApproved(): bool
    {
        return $this->status === self::STATUS_PARTIALLY_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_PARTIALLY_APPROVED,
        ]);
    }

    public function canBeReviewed(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeValidated(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_UNDER_REVIEW,
        ]);
    }

    public function canBeRevoked(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_PARTIALLY_APPROVED,
        ]);
    }

    /**
     * Generate unique exemption number
     */
    public static function generateExemptionNumber(int $academicYearId): string
    {
        $year = date('Y');
        $sequence = static::where('academic_year_id', $academicYearId)->count() + 1;

        return sprintf('DISP-%s-%04d', $year, $sequence);
    }

    /**
     * Get exemption type label
     */
    public function getExemptionTypeLabel(): string
    {
        $labels = [
            self::TYPE_FULL => 'Dispense totale',
            self::TYPE_PARTIAL => 'Dispense partielle',
            self::TYPE_EXEMPTION => 'Exemption',
        ];

        return $labels[$this->exemption_type] ?? $this->exemption_type;
    }

    /**
     * Get reason category label
     */
    public function getReasonCategoryLabel(): string
    {
        $labels = [
            self::REASON_VAE => 'Validation des Acquis de l\'Expérience',
            self::REASON_PRIOR_TRAINING => 'Formation antérieure équivalente',
            self::REASON_PROFESSIONAL_CERTIFICATION => 'Certification professionnelle',
            self::REASON_SPECIAL_SITUATION => 'Situation particulière',
            self::REASON_DOUBLE_DEGREE => 'Double cursus',
            self::REASON_OTHER => 'Autre',
        ];

        return $labels[$this->reason_category] ?? $this->reason_category;
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        $labels = [
            self::STATUS_PENDING => 'En attente',
            self::STATUS_UNDER_REVIEW => 'En cours d\'examen',
            self::STATUS_APPROVED => 'Approuvée',
            self::STATUS_PARTIALLY_APPROVED => 'Partiellement approuvée',
            self::STATUS_REJECTED => 'Rejetée',
            self::STATUS_REVOKED => 'Révoquée',
        ];

        return $labels[$this->status] ?? $this->status;
    }
}
