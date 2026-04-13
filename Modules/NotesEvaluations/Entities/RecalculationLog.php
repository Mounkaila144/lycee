<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Semester;

class RecalculationLog extends Model
{
    protected $connection = 'tenant';

    protected $table = 'recalculation_logs';

    protected $fillable = [
        'student_id',
        'semester_id',
        'module_id',
        'trigger',
        'old_module_average',
        'new_module_average',
        'old_semester_average',
        'new_semester_average',
        'old_module_status',
        'new_module_status',
        'old_semester_status',
        'new_semester_status',
        'credits_before',
        'credits_after',
        'details',
        'recalculated_at',
    ];

    protected function casts(): array
    {
        return [
            'old_module_average' => 'decimal:2',
            'new_module_average' => 'decimal:2',
            'old_semester_average' => 'decimal:2',
            'new_semester_average' => 'decimal:2',
            'credits_before' => 'integer',
            'credits_after' => 'integer',
            'details' => 'array',
            'recalculated_at' => 'datetime',
        ];
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

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    // Computed Attributes

    public function getModuleAverageChangeAttribute(): ?float
    {
        if ($this->old_module_average === null || $this->new_module_average === null) {
            return null;
        }

        return $this->new_module_average - $this->old_module_average;
    }

    public function getSemesterAverageChangeAttribute(): ?float
    {
        if ($this->old_semester_average === null || $this->new_semester_average === null) {
            return null;
        }

        return $this->new_semester_average - $this->old_semester_average;
    }

    public function getCreditsGainedAttribute(): ?int
    {
        if ($this->credits_before === null || $this->credits_after === null) {
            return null;
        }

        return $this->credits_after - $this->credits_before;
    }

    public function getTriggerLabelAttribute(): string
    {
        return match ($this->trigger) {
            'retake_grades_published' => 'Publication notes rattrapage',
            'grade_correction' => 'Correction de note',
            'manual_recalculation' => 'Recalcul manuel',
            'compensation_applied' => 'Application compensation',
            default => $this->trigger,
        };
    }

    // Scopes

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForSemester($query, int $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeByTrigger($query, string $trigger)
    {
        return $query->where('trigger', $trigger);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('recalculated_at', '>=', now()->subDays($days));
    }
}
