<?php

namespace Modules\Exams\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UsersGuard\Entities\Teacher;

class ExamSupervisor extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'exam_session_id',
        'exam_room_assignment_id',
        'teacher_id',
        'role',
        'actual_start_time',
        'actual_end_time',
        'status',
        'is_notified',
        'notified_at',
        'notes',
    ];

    protected $casts = [
        'actual_start_time' => 'datetime:H:i',
        'actual_end_time' => 'datetime:H:i',
        'is_notified' => 'boolean',
        'notified_at' => 'datetime',
    ];

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function roomAssignment(): BelongsTo
    {
        return $this->belongsTo(ExamRoomAssignment::class, 'exam_room_assignment_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    // Scopes
    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', 'absent');
    }

    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopePrincipal($query)
    {
        return $query->where('role', 'principal');
    }
}
