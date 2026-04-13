<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;
use Modules\UsersGuard\Entities\User;

class Transfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'transfers';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public const STATUS_SUBMITTED = 'Submitted';

    public const STATUS_UNDER_REVIEW = 'Under_Review';

    public const STATUS_EQUIVALENCES_PROPOSED = 'Equivalences_Proposed';

    public const STATUS_VALIDATED = 'Validated';

    public const STATUS_INTEGRATED = 'Integrated';

    public const STATUS_REJECTED = 'Rejected';

    public const STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_EQUIVALENCES_PROPOSED,
        self::STATUS_VALIDATED,
        self::STATUS_INTEGRATED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'transfer_number',
        'student_id',
        'firstname',
        'lastname',
        'birthdate',
        'email',
        'phone',
        'origin_institution',
        'origin_program',
        'origin_level',
        'target_program_id',
        'target_level',
        'academic_year_id',
        'transfer_reason',
        'total_ects_claimed',
        'total_ects_granted',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'equivalence_certificate_path',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'total_ects_claimed' => 'integer',
            'total_ects_granted' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function targetProgram(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'target_program_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function equivalences(): HasMany
    {
        return $this->hasMany(Equivalence::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TransferDocument::class);
    }

    /**
     * Scopes
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', self::STATUS_UNDER_REVIEW);
    }

    public function scopeValidated($query)
    {
        return $query->where('status', self::STATUS_VALIDATED);
    }

    public function scopeIntegrated($query)
    {
        return $query->where('status', self::STATUS_INTEGRATED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [
            self::STATUS_SUBMITTED,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_EQUIVALENCES_PROPOSED,
        ]);
    }

    public function scopeForAcademicYear($query, int $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    /**
     * Business Logic
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->firstname} {$this->lastname}";
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isUnderReview(): bool
    {
        return $this->status === self::STATUS_UNDER_REVIEW;
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function isIntegrated(): bool
    {
        return $this->status === self::STATUS_INTEGRATED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function canBeReviewed(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUBMITTED,
            self::STATUS_UNDER_REVIEW,
        ]);
    }

    public function canBeValidated(): bool
    {
        return $this->status === self::STATUS_EQUIVALENCES_PROPOSED;
    }

    public function canBeIntegrated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function canBeRejected(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUBMITTED,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_EQUIVALENCES_PROPOSED,
        ]);
    }

    /**
     * Generate unique transfer number
     */
    public static function generateTransferNumber(int $academicYearId): string
    {
        $year = date('Y');
        $sequence = static::where('academic_year_id', $academicYearId)->count() + 1;

        return sprintf('TRANS-%s-%04d', $year, $sequence);
    }

    /**
     * Statistics
     */
    public function getEquivalenceStatistics(): array
    {
        $equivalences = $this->equivalences;

        return [
            'total' => $equivalences->count(),
            'full' => $equivalences->where('equivalence_type', 'Full')->count(),
            'partial' => $equivalences->where('equivalence_type', 'Partial')->count(),
            'none' => $equivalences->where('equivalence_type', 'None')->count(),
            'exemption' => $equivalences->where('equivalence_type', 'Exemption')->count(),
            'validated' => $equivalences->where('status', 'Validated')->count(),
            'proposed' => $equivalences->where('status', 'Proposed')->count(),
        ];
    }
}
