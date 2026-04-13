<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\Module;

class EctsAllocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'ects_allocations';

    public const TYPE_VALIDATED = 'validated';

    public const TYPE_COMPENSATED = 'compensated';

    public const TYPE_EQUIVALENCE = 'equivalence';

    protected $fillable = [
        'student_id',
        'module_id',
        'semester_result_id',
        'credits_allocated',
        'allocation_type',
        'note',
        'allocated_at',
    ];

    protected function casts(): array
    {
        return [
            'credits_allocated' => 'integer',
            'allocated_at' => 'datetime',
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

    public function semesterResult(): BelongsTo
    {
        return $this->belongsTo(SemesterResult::class);
    }

    // Scopes

    public function scopeValidated($query)
    {
        return $query->where('allocation_type', self::TYPE_VALIDATED);
    }

    public function scopeCompensated($query)
    {
        return $query->where('allocation_type', self::TYPE_COMPENSATED);
    }

    public function scopeEquivalence($query)
    {
        return $query->where('allocation_type', self::TYPE_EQUIVALENCE);
    }

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    // Computed

    public function getAllocationTypeLabelAttribute(): string
    {
        return match ($this->allocation_type) {
            self::TYPE_VALIDATED => 'Validé',
            self::TYPE_COMPENSATED => 'Compensé',
            self::TYPE_EQUIVALENCE => 'Équivalence',
            default => 'Inconnu',
        };
    }

    // Business Logic

    public function isValidated(): bool
    {
        return $this->allocation_type === self::TYPE_VALIDATED;
    }

    public function isCompensated(): bool
    {
        return $this->allocation_type === self::TYPE_COMPENSATED;
    }

    public function isEquivalence(): bool
    {
        return $this->allocation_type === self::TYPE_EQUIVALENCE;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Modules\NotesEvaluations\Database\Factories\EctsAllocationFactory::new();
    }
}
