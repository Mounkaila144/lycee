<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Entities\AcademicYear;

class ReenrollmentCampaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'reenrollment_campaigns';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public const STATUS_DRAFT = 'Draft';

    public const STATUS_ACTIVE = 'Active';

    public const STATUS_CLOSED = 'Closed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_CLOSED,
    ];

    protected $fillable = [
        'from_academic_year_id',
        'to_academic_year_id',
        'name',
        'start_date',
        'end_date',
        'eligible_programs',
        'eligible_levels',
        'required_documents',
        'fees_config',
        'min_ects_required',
        'check_financial_clearance',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'eligible_programs' => 'array',
            'eligible_levels' => 'array',
            'required_documents' => 'array',
            'fees_config' => 'array',
            'min_ects_required' => 'integer',
            'check_financial_clearance' => 'boolean',
        ];
    }

    /**
     * Relations
     */
    public function fromAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'from_academic_year_id');
    }

    public function toAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'to_academic_year_id');
    }

    public function reenrollments(): HasMany
    {
        return $this->hasMany(Reenrollment::class, 'campaign_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    public function scopeOpenForRegistration($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    /**
     * Business Logic
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->start_date->lte(now())
            && $this->end_date->gte(now());
    }

    public function isProgramEligible(int $programId): bool
    {
        if (empty($this->eligible_programs)) {
            return true;
        }

        return in_array($programId, $this->eligible_programs);
    }

    public function isLevelEligible(string $level): bool
    {
        if (empty($this->eligible_levels)) {
            return true;
        }

        return in_array($level, $this->eligible_levels);
    }

    public function getFeesForLevel(string $level): ?float
    {
        if (empty($this->fees_config)) {
            return null;
        }

        return $this->fees_config[$level] ?? null;
    }

    /**
     * Statistics
     */
    public function getStatistics(): array
    {
        $reenrollments = $this->reenrollments();

        return [
            'total' => $reenrollments->count(),
            'draft' => $reenrollments->where('status', 'Draft')->count(),
            'submitted' => $reenrollments->where('status', 'Submitted')->count(),
            'validated' => $reenrollments->where('status', 'Validated')->count(),
            'rejected' => $reenrollments->where('status', 'Rejected')->count(),
            'eligible' => $reenrollments->where('eligibility_status', 'Eligible')->count(),
            'not_eligible' => $reenrollments->where('eligibility_status', 'Not_Eligible')->count(),
            'redoing' => $reenrollments->where('is_redoing', true)->count(),
            'reorientation' => $reenrollments->where('is_reorientation', true)->count(),
        ];
    }
}
