<?php

namespace Modules\Exams\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Timetable\Entities\Room;

class ExamRoomAssignment extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    protected $fillable = [
        'exam_session_id',
        'room_id',
        'capacity',
        'assigned_students',
        'seat_start_number',
        'seat_end_number',
        'notes',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'assigned_students' => 'integer',
        'seat_start_number' => 'integer',
        'seat_end_number' => 'integer',
    ];

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function supervisors(): HasMany
    {
        return $this->hasMany(ExamSupervisor::class);
    }

    public function attendanceSheets(): HasMany
    {
        return $this->hasMany(ExamAttendanceSheet::class);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->whereColumn('assigned_students', '<', 'capacity');
    }

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('exam_session_id', $sessionId);
    }
}
