<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\Semester;

class SemesterResult extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'semester_results';

    protected $fillable = [
        'student_id',
        'semester_id',
        'average',
        'is_final',
        'is_validated',
        'global_status',
        'validated_modules_count',
        'compensated_modules_count',
        'failed_modules_count',
        'can_progress_next_year',
        'rank',
        'total_ranked',
        'validation_blocked_by_eliminatory',
        'blocking_reasons',
        'total_credits',
        'acquired_credits',
        'missing_credits',
        'success_rate',
        'missing_modules_count',
        'calculated_at',
        'published_at',
        'retake_session_completed',
        'final_status',
        'attestation_file_path',
        'final_published_at',
        'year_locked_at',
    ];

    protected function casts(): array
    {
        return [
            'average' => 'decimal:2',
            'is_final' => 'boolean',
            'is_validated' => 'boolean',
            'validated_modules_count' => 'integer',
            'compensated_modules_count' => 'integer',
            'failed_modules_count' => 'integer',
            'can_progress_next_year' => 'boolean',
            'rank' => 'integer',
            'total_ranked' => 'integer',
            'validation_blocked_by_eliminatory' => 'boolean',
            'blocking_reasons' => 'array',
            'total_credits' => 'integer',
            'acquired_credits' => 'integer',
            'missing_credits' => 'integer',
            'success_rate' => 'decimal:2',
            'missing_modules_count' => 'integer',
            'calculated_at' => 'datetime',
            'published_at' => 'datetime',
            'retake_session_completed' => 'boolean',
            'final_published_at' => 'datetime',
            'year_locked_at' => 'datetime',
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

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function ectsAllocations(): HasMany
    {
        return $this->hasMany(EctsAllocation::class);
    }

    public function juryDecisions(): HasMany
    {
        return $this->hasMany(JuryDecision::class);
    }

    // Scopes

    public function scopeFinal($query)
    {
        return $query->where('is_final', true);
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    public function scopeValidated($query)
    {
        return $query->where('is_validated', true);
    }

    public function scopeNotValidated($query)
    {
        return $query->where('is_validated', false);
    }

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForSemester($query, int $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    // Computed Attributes

    public function getStatusAttribute(): string
    {
        if ($this->average === null) {
            return 'ABS';
        }

        if (! $this->is_final) {
            return 'Provisoire';
        }

        return $this->is_validated ? 'Validé' : 'Non validé';
    }

    public function getGlobalStatusLabelAttribute(): string
    {
        return match ($this->global_status) {
            'validated' => 'Semestre validé',
            'partially_validated' => 'Partiellement validé',
            'to_retake' => 'Rattrapage requis',
            'deferred' => 'Ajourné',
            default => 'Non déterminé',
        };
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->global_status) {
            'validated' => 'green',
            'partially_validated' => 'orange',
            'to_retake' => 'red',
            'deferred' => 'gray',
            default => 'gray',
        };
    }

    public function getRankDisplayAttribute(): ?string
    {
        if ($this->rank === null || $this->total_ranked === null) {
            return null;
        }

        return "{$this->rank}ème sur {$this->total_ranked}";
    }

    public function getMentionAttribute(): string
    {
        if ($this->average === null) {
            return 'Non évalué';
        }

        if ($this->average >= 16) {
            return 'Très Bien';
        }

        if ($this->average >= 14) {
            return 'Bien';
        }

        if ($this->average >= 12) {
            return 'Assez Bien';
        }

        if ($this->average >= 10) {
            return 'Passable';
        }

        return 'Non admis';
    }

    public function getPassedAttribute(): bool
    {
        return $this->average !== null && $this->average >= 10;
    }

    public function getCompletionPercentageAttribute(): float
    {
        return $this->total_credits > 0
            ? round(($this->acquired_credits / $this->total_credits) * 100, 2)
            : 0;
    }

    public function getIsPublishedAttribute(): bool
    {
        return $this->published_at !== null;
    }

    public function getFinalStatusLabelAttribute(): string
    {
        return match ($this->final_status) {
            'admitted' => 'Admis',
            'admitted_with_debts' => 'Admis avec dettes',
            'deferred_final' => 'Ajourné définitif',
            'repeating' => 'Redoublement',
            default => 'En cours',
        };
    }

    public function getFinalStatusColorAttribute(): string
    {
        return match ($this->final_status) {
            'admitted' => 'green',
            'admitted_with_debts' => 'yellow',
            'deferred_final' => 'red',
            'repeating' => 'gray',
            default => 'blue',
        };
    }

    public function getIsFinalPublishedAttribute(): bool
    {
        return $this->final_published_at !== null;
    }

    public function getIsYearLockedAttribute(): bool
    {
        return $this->year_locked_at !== null;
    }

    // Business Logic

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function publish(): void
    {
        $this->update(['published_at' => now()]);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Modules\NotesEvaluations\Database\Factories\SemesterResultFactory::new();
    }
}
