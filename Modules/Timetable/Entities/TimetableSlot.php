<?php

namespace Modules\Timetable\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Entities\Group;
use Modules\StructureAcademique\Entities\Module;
use Modules\StructureAcademique\Entities\Semester;
use Modules\Timetable\Database\Factories\TimetableSlotFactory;
use Modules\UsersGuard\Entities\User;

class TimetableSlot extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'timetable_slots';

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): TimetableSlotFactory
    {
        return TimetableSlotFactory::new();
    }

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'module_id',
        'teacher_id',
        'group_id',
        'room_id',
        'semester_id',
        'day_of_week',
        'start_time',
        'end_time',
        'type',
        'is_recurring',
        'specific_date',
        'notes',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'is_recurring' => 'boolean',
            'specific_date' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public const VALID_DAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

    public const VALID_TYPES = ['CM', 'TD', 'TP'];

    public const STANDARD_SLOTS = [
        ['08:00', '10:00'],
        ['10:00', '12:00'],
        ['14:00', '16:00'],
        ['16:00', '18:00'],
    ];

    /**
     * Relations
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(TimetableChange::class);
    }

    /**
     * Scopes
     */
    public function scopeBySemester($query, int $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeByTeacher($query, int $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeByGroup($query, int $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    public function scopeByRoom($query, int $roomId)
    {
        return $query->where('room_id', $roomId);
    }

    public function scopeByDay($query, string $dayOfWeek)
    {
        return $query->where('day_of_week', $dayOfWeek);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    public function scopeOverlapping($query, string $dayOfWeek, string $startTime, string $endTime, int $semesterId)
    {
        return $query->where('semester_id', $semesterId)
            ->where('day_of_week', $dayOfWeek)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($q2) use ($startTime, $endTime) {
                    $q2->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                });
            });
    }

    /**
     * Accessors
     */
    public function getDurationAttribute(): int
    {
        $start = strtotime($this->start_time);
        $end = strtotime($this->end_time);

        return ($end - $start) / 60; // durée en minutes
    }

    public function getDurationHoursAttribute(): float
    {
        return $this->duration / 60;
    }

    public function getTimeRangeAttribute(): string
    {
        return substr($this->start_time, 0, 5).' - '.substr($this->end_time, 0, 5);
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->module?->code} - {$this->type} ({$this->day_of_week} {$this->time_range})";
    }

    /**
     * Business Logic Methods
     */
    public function isRecurring(): bool
    {
        return $this->is_recurring === true;
    }

    public function overlapsWithTime(string $startTime, string $endTime): bool
    {
        return $this->start_time < $endTime && $this->end_time > $startTime;
    }

    public function overlapsWithSlot(TimetableSlot $other): bool
    {
        if ($this->semester_id !== $other->semester_id) {
            return false;
        }

        if ($this->day_of_week !== $other->day_of_week) {
            return false;
        }

        return $this->overlapsWithTime($other->start_time, $other->end_time);
    }

    public function hasTeacherConflict(TimetableSlot $other): bool
    {
        if ($this->teacher_id !== $other->teacher_id) {
            return false;
        }

        return $this->overlapsWithSlot($other);
    }

    public function hasRoomConflict(TimetableSlot $other): bool
    {
        if ($this->room_id !== $other->room_id) {
            return false;
        }

        return $this->overlapsWithSlot($other);
    }

    public function hasGroupConflict(TimetableSlot $other): bool
    {
        if ($this->group_id !== $other->group_id) {
            return false;
        }

        return $this->overlapsWithSlot($other);
    }

    /**
     * Validation du créneau
     */
    public function isValidDuration(): bool
    {
        $duration = $this->duration;

        return $duration >= 60 && $duration <= 240; // Entre 1h et 4h
    }

    public function isStandardSlot(): bool
    {
        foreach (self::STANDARD_SLOTS as [$start, $end]) {
            if ($this->start_time === $start.':00' && $this->end_time === $end.':00') {
                return true;
            }
        }

        return false;
    }
}
