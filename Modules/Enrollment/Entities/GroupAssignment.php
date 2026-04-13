<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Database\Factories\GroupAssignmentFactory;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Module;
use Modules\UsersGuard\Entities\User;

class GroupAssignment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): GroupAssignmentFactory
    {
        return GroupAssignmentFactory::new();
    }

    protected $connection = 'tenant';

    protected $table = 'group_assignments';

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'student_id',
        'group_id',
        'module_id',
        'academic_year_id',
        'assignment_method',
        'assigned_by',
        'assignment_reason',
        'assigned_at',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public const VALID_METHODS = ['Automatic', 'Manual'];

    /**
     * Relations
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
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
    public function scopeByStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeByGroup($query, int $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    public function scopeByModule($query, int $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeByAcademicYear($query, int $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeAutomatic($query)
    {
        return $query->where('assignment_method', 'Automatic');
    }

    public function scopeManual($query)
    {
        return $query->where('assignment_method', 'Manual');
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
}
