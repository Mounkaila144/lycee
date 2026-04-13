<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Enrollment\Database\Factories\OptionAssignmentFactory;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\UsersGuard\Entities\User;

class OptionAssignment extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'option_assignments';

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): OptionAssignmentFactory
    {
        return OptionAssignmentFactory::new();
    }

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'student_id',
        'option_id',
        'academic_year_id',
        'choice_rank_obtained',
        'assignment_method',
        'assigned_by',
        'assignment_notes',
        'assigned_at',
    ];

    /**
     * Casts
     */
    protected function casts(): array
    {
        return [
            'choice_rank_obtained' => 'integer',
            'assigned_at' => 'datetime',
        ];
    }

    /**
     * Relations
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Scopes
     */
    public function scopeAutomatic(Builder $query): Builder
    {
        return $query->where('assignment_method', 'Automatic');
    }

    public function scopeManual(Builder $query): Builder
    {
        return $query->where('assignment_method', 'Manual');
    }

    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForOption(Builder $query, int $optionId): Builder
    {
        return $query->where('option_id', $optionId);
    }

    public function scopeForAcademicYear(Builder $query, int $academicYearId): Builder
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeFirstChoiceObtained(Builder $query): Builder
    {
        return $query->where('choice_rank_obtained', 1);
    }

    /**
     * Business Logic Methods
     */
    public function isAutomatic(): bool
    {
        return $this->assignment_method === 'Automatic';
    }

    public function isManual(): bool
    {
        return $this->assignment_method === 'Manual';
    }

    public function gotFirstChoice(): bool
    {
        return $this->choice_rank_obtained === 1;
    }

    public function gotSecondChoice(): bool
    {
        return $this->choice_rank_obtained === 2;
    }

    public function gotThirdChoice(): bool
    {
        return $this->choice_rank_obtained === 3;
    }

    public function getChoiceRankObtainedLabel(): string
    {
        return match ($this->choice_rank_obtained) {
            1 => '1er vœu',
            2 => '2e vœu',
            3 => '3e vœu',
            default => "{$this->choice_rank_obtained}e vœu",
        };
    }

    public function getAssignmentMethodLabel(): string
    {
        return match ($this->assignment_method) {
            'Automatic' => 'Automatique',
            'Manual' => 'Manuel',
            default => $this->assignment_method,
        };
    }
}
