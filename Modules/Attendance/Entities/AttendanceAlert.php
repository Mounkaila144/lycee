<?php

namespace Modules\Attendance\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\StructureAcademique\Entities\Semester;
use Modules\UsersGuard\Entities\User;

class AttendanceAlert extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'student_id',
        'semester_id',
        'alert_type',
        'absence_count',
        'absence_rate',
        'threshold_value',
        'message',
        'status',
        'notified_at',
        'acknowledged_at',
        'acknowledged_by',
    ];

    protected function casts(): array
    {
        return [
            'absence_count' => 'integer',
            'absence_rate' => 'decimal:2',
            'threshold_value' => 'integer',
            'notified_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeNotified($query)
    {
        return $query->where('status', 'notified');
    }

    public function getAlertTypeLabel(): string
    {
        return match ($this->alert_type) {
            'threshold_warning' => 'Avertissement seuil',
            'threshold_critical' => 'Seuil critique',
            'repeated_absences' => 'Absences répétées',
            default => 'Alerte',
        };
    }
}
