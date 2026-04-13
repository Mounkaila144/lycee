<?php

namespace Modules\Exams\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Enrollment\Entities\Student;
use Modules\UsersGuard\Entities\User;

class ExamAttendanceSheet extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'exam_session_id',
        'exam_room_assignment_id',
        'student_id',
        'seat_number',
        'status',
        'arrival_time',
        'submission_time',
        'has_submitted',
        'notes',
        'signature_path',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'arrival_time' => 'datetime:H:i',
        'submission_time' => 'datetime:H:i',
        'has_submitted' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function roomAssignment(): BelongsTo
    {
        return $this->belongsTo(ExamRoomAssignment::class, 'exam_room_assignment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
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

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }
}
