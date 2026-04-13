<?php

namespace Modules\Exams\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\EvaluationPeriod;
use Modules\StructureAcademique\Entities\Module as AcademicModule;
use Modules\UsersGuard\Entities\User;

class ExamSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'module_id',
        'evaluation_period_id',
        'academic_year_id',
        'title',
        'description',
        'type',
        'exam_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'total_capacity',
        'status',
        'instructions',
        'allowed_materials',
        'is_published',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'allowed_materials' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(AcademicModule::class, 'module_id');
    }

    public function evaluationPeriod(): BelongsTo
    {
        return $this->belongsTo(EvaluationPeriod::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(ExamRoomAssignment::class);
    }

    public function supervisors(): HasMany
    {
        return $this->hasMany(ExamSupervisor::class);
    }

    public function attendanceSheets(): HasMany
    {
        return $this->hasMany(ExamAttendanceSheet::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(ExamIncident::class);
    }

    // Scopes
    public function scopeUpcoming($query)
    {
        return $query->where('exam_date', '>=', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->orderBy('exam_date')
            ->orderBy('start_time');
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('exam_date', $date);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
