<?php

namespace Modules\Timetable\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Timetable\Database\Factories\TeacherPreferenceFactory;
use Modules\UsersGuard\Entities\User;

class TeacherPreference extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected static function newFactory(): TeacherPreferenceFactory
    {
        return TeacherPreferenceFactory::new();
    }

    protected $fillable = [
        'teacher_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_preferred',
        'priority',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_preferred' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Scope pour récupérer les préférences positives
     */
    public function scopePreferred($query)
    {
        return $query->where('is_preferred', true);
    }

    /**
     * Scope pour récupérer les créneaux à éviter
     */
    public function scopeToAvoid($query)
    {
        return $query->where('is_preferred', false);
    }

    /**
     * Scope pour un jour spécifique
     */
    public function scopeForDay($query, string $day)
    {
        return $query->where('day_of_week', $day);
    }

    /**
     * Vérifie si un créneau chevauche cette préférence
     */
    public function overlaps(string $startTime, string $endTime): bool
    {
        return ! ($this->end_time <= $startTime || $this->start_time >= $endTime);
    }
}
