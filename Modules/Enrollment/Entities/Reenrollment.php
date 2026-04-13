<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Entities\Programme;
use Modules\UsersGuard\Entities\User;

class Reenrollment extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'reenrollments';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public const STATUS_DRAFT = 'Draft';

    public const STATUS_SUBMITTED = 'Submitted';

    public const STATUS_VALIDATED = 'Validated';

    public const STATUS_REJECTED = 'Rejected';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_VALIDATED,
        self::STATUS_REJECTED,
    ];

    public const ELIGIBILITY_ELIGIBLE = 'Eligible';

    public const ELIGIBILITY_NOT_ELIGIBLE = 'Not_Eligible';

    public const ELIGIBILITY_PENDING = 'Pending';

    protected $fillable = [
        'campaign_id',
        'student_id',
        'previous_enrollment_id',
        'previous_level',
        'target_level',
        'target_program_id',
        'is_redoing',
        'is_reorientation',
        'personal_data_updates',
        'uploaded_documents',
        'has_accepted_rules',
        'eligibility_status',
        'eligibility_notes',
        'status',
        'validated_by',
        'submitted_at',
        'validated_at',
        'rejection_reason',
        'confirmation_pdf_path',
        'new_enrollment_id',
    ];

    protected function casts(): array
    {
        return [
            'is_redoing' => 'boolean',
            'is_reorientation' => 'boolean',
            'personal_data_updates' => 'array',
            'uploaded_documents' => 'array',
            'has_accepted_rules' => 'boolean',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ReenrollmentCampaign::class, 'campaign_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function previousEnrollment(): BelongsTo
    {
        return $this->belongsTo(PedagogicalEnrollment::class, 'previous_enrollment_id');
    }

    public function targetProgram(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'target_program_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function newEnrollment(): BelongsTo
    {
        return $this->belongsTo(PedagogicalEnrollment::class, 'new_enrollment_id');
    }

    /**
     * Scopes
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeValidated($query)
    {
        return $query->where('status', self::STATUS_VALIDATED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeEligible($query)
    {
        return $query->where('eligibility_status', self::ELIGIBILITY_ELIGIBLE);
    }

    public function scopeRedoing($query)
    {
        return $query->where('is_redoing', true);
    }

    public function scopeReorientation($query)
    {
        return $query->where('is_reorientation', true);
    }

    public function scopeForCampaign($query, int $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    /**
     * Business Logic
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isEligible(): bool
    {
        return $this->eligibility_status === self::ELIGIBILITY_ELIGIBLE;
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === self::STATUS_DRAFT
            && $this->eligibility_status === self::ELIGIBILITY_ELIGIBLE
            && $this->has_accepted_rules
            && $this->campaign->isOpen();
    }

    public function canBeValidated(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function canBeRejected(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    /**
     * Get target level label
     */
    public function getTargetLevelLabel(): string
    {
        $labels = [
            'L1' => 'Licence 1',
            'L2' => 'Licence 2',
            'L3' => 'Licence 3',
            'M1' => 'Master 1',
            'M2' => 'Master 2',
        ];

        return $labels[$this->target_level] ?? $this->target_level;
    }
}
