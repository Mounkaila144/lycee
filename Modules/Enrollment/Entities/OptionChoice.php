<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Enrollment\Database\Factories\OptionChoiceFactory;
use Modules\StructureAcademique\Entities\AcademicYear;

class OptionChoice extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $table = 'option_choices';

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): OptionChoiceFactory
    {
        return OptionChoiceFactory::new();
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
        'choice_rank',
        'status',
        'motivation',
    ];

    /**
     * Casts
     */
    protected function casts(): array
    {
        return [
            'choice_rank' => 'integer',
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

    /**
     * Scopes
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'Pending');
    }

    public function scopeValidated(Builder $query): Builder
    {
        return $query->where('status', 'Validated');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'Rejected');
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

    public function scopeFirstChoice(Builder $query): Builder
    {
        return $query->where('choice_rank', 1);
    }

    public function scopeOrderedByRank(Builder $query): Builder
    {
        return $query->orderBy('choice_rank', 'asc');
    }

    /**
     * Business Logic Methods
     */
    public function isPending(): bool
    {
        return $this->status === 'Pending';
    }

    public function isValidated(): bool
    {
        return $this->status === 'Validated';
    }

    public function isRejected(): bool
    {
        return $this->status === 'Rejected';
    }

    public function isFirstChoice(): bool
    {
        return $this->choice_rank === 1;
    }

    public function isSecondChoice(): bool
    {
        return $this->choice_rank === 2;
    }

    public function isThirdChoice(): bool
    {
        return $this->choice_rank === 3;
    }

    public function getChoiceRankLabel(): string
    {
        return match ($this->choice_rank) {
            1 => '1er vœu',
            2 => '2e vœu',
            3 => '3e vœu',
            default => "{$this->choice_rank}e vœu",
        };
    }

    public function validate(): bool
    {
        $this->status = 'Validated';

        return $this->save();
    }

    public function reject(): bool
    {
        $this->status = 'Rejected';

        return $this->save();
    }
}
