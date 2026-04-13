<?php

namespace Modules\Timetable\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Timetable\Database\Factories\RoomFactory;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'rooms';

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): RoomFactory
    {
        return RoomFactory::new();
    }

    /**
     * Resolve the route binding for the model.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return static::on('tenant')->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    protected $fillable = [
        'code',
        'name',
        'type',
        'building',
        'floor',
        'capacity',
        'equipment',
        'is_active',
        'description',
        'unavailable_reason',
        'unavailable_from',
        'unavailable_to',
    ];

    /**
     * Laravel 12: casts() method
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'equipment' => 'array',
            'is_active' => 'boolean',
            'unavailable_from' => 'datetime',
            'unavailable_to' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public const VALID_TYPES = ['Amphi', 'Salle', 'Labo', 'Salle_Info', 'Autre'];

    /**
     * Relations
     */
    public function timetableSlots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWithMinCapacity($query, int $minCapacity)
    {
        return $query->where('capacity', '>=', $minCapacity);
    }

    public function scopeAvailableForSlot($query, string $dayOfWeek, string $startTime, string $endTime, int $semesterId, ?int $excludeSlotId = null)
    {
        return $query->whereDoesntHave('timetableSlots', function ($q) use ($dayOfWeek, $startTime, $endTime, $semesterId, $excludeSlotId) {
            $q->where('semester_id', $semesterId)
                ->where('day_of_week', $dayOfWeek)
                ->where(function ($q2) use ($startTime, $endTime) {
                    $q2->where(function ($q3) use ($startTime, $endTime) {
                        $q3->where('start_time', '<', $endTime)
                            ->where('end_time', '>', $startTime);
                    });
                });

            if ($excludeSlotId) {
                $q->where('id', '!=', $excludeSlotId);
            }
        });
    }

    /**
     * Accessors
     */
    public function getFullNameAttribute(): string
    {
        $parts = [$this->code, $this->name];

        if ($this->building) {
            $parts[] = "({$this->building})";
        }

        return implode(' - ', array_filter($parts));
    }

    /**
     * Business Logic Methods
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function isSuitableForType(string $sessionType): bool
    {
        return match ($sessionType) {
            'CM' => in_array($this->type, ['Amphi', 'Salle']),
            'TD' => in_array($this->type, ['Salle', 'Amphi']),
            'TP' => in_array($this->type, ['Labo', 'Salle_Info']),
            default => true,
        };
    }

    public function hasEnoughCapacity(int $groupSize): bool
    {
        return $this->capacity >= $groupSize;
    }

    public function isAvailableAt(string $dayOfWeek, string $startTime, string $endTime, int $semesterId, ?int $excludeSlotId = null): bool
    {
        $query = $this->timetableSlots()
            ->where('semester_id', $semesterId)
            ->where('day_of_week', $dayOfWeek)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($q2) use ($startTime, $endTime) {
                    $q2->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                });
            });

        if ($excludeSlotId) {
            $query->where('id', '!=', $excludeSlotId);
        }

        return ! $query->exists();
    }

    public function getOccupationForSemester(int $semesterId): array
    {
        $slots = $this->timetableSlots()
            ->where('semester_id', $semesterId)
            ->get();

        $totalHours = 0;
        foreach ($slots as $slot) {
            $start = strtotime($slot->start_time);
            $end = strtotime($slot->end_time);
            $totalHours += ($end - $start) / 3600;
        }

        // 6 jours * 10h = 60h max par semaine
        $maxHoursPerWeek = 60;
        $occupationRate = $maxHoursPerWeek > 0 ? ($totalHours / $maxHoursPerWeek) * 100 : 0;

        return [
            'total_slots' => $slots->count(),
            'total_hours_per_week' => $totalHours,
            'occupation_rate' => round($occupationRate, 2),
        ];
    }
}
