<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\NotesEvaluations\Database\Factories\GradeValidationFactory;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\TenantUser;

class GradeValidation extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): GradeValidationFactory
    {
        return GradeValidationFactory::new();
    }

    protected $connection = 'tenant';

    protected $table = 'grade_validations';

    protected $fillable = [
        'module_id',
        'evaluation_id',
        'academic_year_id',
        'semester_id',
        'submitted_by',
        'status',
        'validated_by',
        'submitted_at',
        'validated_at',
        'published_at',
        'scheduled_publish_at',
        'rejection_reason',
        'statistics',
        'anomalies',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'published_at' => 'datetime',
            'scheduled_publish_at' => 'datetime',
            'statistics' => 'array',
            'anomalies' => 'array',
        ];
    }

    // Relations

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ModuleEvaluationConfig::class, 'evaluation_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'submitted_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'validated_by');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'Approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'Rejected');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'Published');
    }

    public function scopeForModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeForAcademicYear($query, int $yearId)
    {
        return $query->where('academic_year_id', $yearId);
    }

    // Business Logic

    public function isPending(): bool
    {
        return $this->status === 'Pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'Approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'Rejected';
    }

    public function isPublished(): bool
    {
        return $this->status === 'Published';
    }

    public function canBeValidated(): bool
    {
        return $this->status === 'Pending';
    }

    public function canBePublished(): bool
    {
        return $this->status === 'Approved';
    }

    public function hasAnomalies(): bool
    {
        return ! empty($this->anomalies);
    }

    public function approve(TenantUser $validator, ?string $notes = null): void
    {
        $this->update([
            'status' => 'Approved',
            'validated_by' => $validator->id,
            'validated_at' => now(),
            'notes' => $notes,
        ]);
    }

    public function reject(TenantUser $validator, string $reason): void
    {
        $this->update([
            'status' => 'Rejected',
            'validated_by' => $validator->id,
            'validated_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function publish(): void
    {
        $this->update([
            'status' => 'Published',
            'published_at' => now(),
        ]);
    }
}
