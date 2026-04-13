<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Semester;

class ModuleGrade extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'module_grades';

    protected $fillable = [
        'student_id',
        'module_id',
        'semester_id',
        'average',
        'is_final',
        'missing_evaluations_count',
        'status',
        'rank',
        'total_ranked',
        'compensation_applied_at',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'average' => 'decimal:2',
            'is_final' => 'boolean',
            'missing_evaluations_count' => 'integer',
            'rank' => 'integer',
            'total_ranked' => 'integer',
            'compensation_applied_at' => 'datetime',
            'calculated_at' => 'datetime',
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

    // Scopes

    public function scopeFinal($query)
    {
        return $query->where('is_final', true);
    }

    public function scopeProvisoire($query)
    {
        return $query->where('is_final', false);
    }

    public function scopeValidated($query)
    {
        return $query->where('average', '>=', 10);
    }

    public function scopeNotValidated($query)
    {
        return $query->where(function ($q) {
            $q->where('average', '<', 10)
                ->orWhereNull('average');
        });
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

    public function scopeCompensated($query)
    {
        return $query->where('status', 'Compensated');
    }

    public function scopeCompensable($query, float $minGrade = 0, float $validationThreshold = 10)
    {
        return $query->whereNotNull('average')
            ->where('average', '>=', $minGrade)
            ->where('average', '<', $validationThreshold);
    }

    // Computed Attributes

    public function getStatusLabelAttribute(): string
    {
        if ($this->average === null) {
            return 'ABS';
        }

        if (! $this->is_final) {
            return 'Provisoire';
        }

        if ($this->status === 'Compensated') {
            return 'Compensé';
        }

        return $this->average >= 10 ? 'Validé' : 'Non validé';
    }

    public function getIsCompensatedAttribute(): bool
    {
        return $this->status === 'Compensated' && $this->compensation_applied_at !== null;
    }

    public function getIsValidatedAttribute(): bool
    {
        return $this->average !== null && $this->average >= 10;
    }

    public function getIsAbsentAttribute(): bool
    {
        return $this->average === null && $this->is_final;
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

    // Business Logic

    public function isValidated(): bool
    {
        return $this->average !== null && $this->average >= 10;
    }

    public function needsRetake(): bool
    {
        return $this->is_final && ($this->average === null || $this->average < 10);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Modules\NotesEvaluations\Database\Factories\ModuleGradeFactory::new();
    }
}
