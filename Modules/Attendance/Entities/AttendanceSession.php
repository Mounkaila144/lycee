<?php

namespace Modules\Attendance\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\UsersGuard\Entities\User;

class AttendanceSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'timetable_slot_id',
        'session_date',
        'start_time',
        'end_time',
        'status',
        'method',
        'created_by',
        'completed_by',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function timetableSlot()
    {
        return $this->belongsTo(TimetableSlot::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function records()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('session_date', $date);
    }
}
