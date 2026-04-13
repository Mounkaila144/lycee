<?php

namespace Modules\NotesEvaluations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;

class CompensationLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'compensation_logs';

    protected $fillable = [
        'student_id',
        'module_id',
        'semester_id',
        'module_average',
        'semester_average',
        'compensation_reason',
        'applied_at',
        'applied_by',
    ];

    protected function casts(): array
    {
        return [
            'module_average' => 'decimal:2',
            'semester_average' => 'decimal:2',
            'applied_at' => 'datetime',
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

    public function appliedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
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

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('applied_at', '>=', now()->subDays($days));
    }
}
