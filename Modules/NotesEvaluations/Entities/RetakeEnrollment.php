<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Semester;

class RetakeEnrollment extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'retake_enrollments';

    protected $fillable = [
        'student_id',
        'module_id',
        'semester_id',
        'original_average',
        'status',
        'identified_at',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'original_average' => 'decimal:2',
            'identified_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    /**
     * Resolve route binding for tenant connection
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    // Relations

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function retakeGrade(): HasOne
    {
        return $this->hasOne(RetakeGrade::class);
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeGraded($query)
    {
        return $query->where('status', 'graded');
    }

    public function scopeValidated($query)
    {
        return $query->where('status', 'validated');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeForSemester($query, int $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'scheduled', 'graded']);
    }

    // Computed Attributes

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'En attente',
            'scheduled' => 'Programmé',
            'graded' => 'Noté',
            'validated' => 'Validé',
            'cancelled' => 'Annulé',
            default => 'Inconnu',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'orange',
            'scheduled' => 'blue',
            'graded' => 'purple',
            'validated' => 'green',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    public function getGapToValidationAttribute(): ?float
    {
        if ($this->original_average === null) {
            return null;
        }

        $config = GradeConfig::getConfig();
        $threshold = $config->min_module_average ?? 10.00;

        return max(0, $threshold - $this->original_average);
    }

    // Business Logic

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isGraded(): bool
    {
        return $this->status === 'graded';
    }

    public function isValidated(): bool
    {
        return $this->status === 'validated';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canBeScheduled(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeGraded(): bool
    {
        return in_array($this->status, ['pending', 'scheduled']);
    }

    public function schedule(?string $scheduledAt = null): void
    {
        $this->update([
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt ? now()->parse($scheduledAt) : now(),
        ]);
    }

    public function markAsGraded(): void
    {
        $this->update(['status' => 'graded']);
    }

    public function markAsValidated(): void
    {
        $this->update(['status' => 'validated']);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Modules\NotesEvaluations\Database\Factories\RetakeEnrollmentFactory::new();
    }
}
