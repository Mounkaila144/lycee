<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Database\Factories\GroupFactory;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Programme;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;

class Group extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): GroupFactory
    {
        return GroupFactory::new();
    }

    protected $connection = 'tenant';

    protected $table = 'groups';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'module_id',
        'program_id',
        'level',
        'academic_year_id',
        'semester_id',
        'code',
        'name',
        'type',
        'capacity_min',
        'capacity_max',
        'teacher_id',
        'room_id',
        'status',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'capacity_min' => 'integer',
            'capacity_max' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public const VALID_TYPES = ['CM', 'TD', 'TP'];

    public const VALID_STATUSES = ['Active', 'Inactive'];

    public const VALID_LEVELS = ['L1', 'L2', 'L3', 'M1', 'M2'];

    /**
     * Relations
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'program_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(GroupAssignment::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(GroupAssignment::class)->with('student');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'Inactive');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeByProgramme($query, int $programId)
    {
        return $query->where('program_id', $programId);
    }

    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeByAcademicYear($query, int $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeBySemester($query, int $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    /**
     * Accessors
     */
    public function getCurrentCountAttribute(): int
    {
        return $this->assignments()->count();
    }

    public function getFillRateAttribute(): float
    {
        if ($this->capacity_max <= 0) {
            return 0;
        }

        return round(($this->current_count / $this->capacity_max) * 100, 2);
    }

    public function getAvailableSlotsAttribute(): int
    {
        return max(0, $this->capacity_max - $this->current_count);
    }

    public function getIsFullAttribute(): bool
    {
        return $this->current_count >= $this->capacity_max;
    }

    public function getIsBelowMinimumAttribute(): bool
    {
        return $this->current_count < $this->capacity_min;
    }

    /**
     * Business Logic Methods
     */
    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    public function canAcceptMoreStudents(): bool
    {
        return $this->isActive() && ! $this->is_full;
    }

    public function hasCapacityFor(int $count): bool
    {
        return $this->available_slots >= $count;
    }

    public function hasStudent(int $studentId): bool
    {
        return $this->assignments()->where('student_id', $studentId)->exists();
    }
}
