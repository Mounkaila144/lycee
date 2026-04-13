<?php

namespace Modules\Timetable\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Timetable\Entities\Room;
use Modules\Timetable\Entities\TimetableSlot;

class RoomAvailabilityService
{
    /**
     * Mapping des types de séance vers les types de salles appropriées
     */
    private const TYPE_MAPPING = [
        'CM' => ['Amphi', 'Salle'],
        'TD' => ['Salle', 'Amphi'],
        'TP' => ['Labo', 'Salle_Info'],
    ];

    /**
     * Suggère des salles appropriées pour une séance
     */
    public function getSuggestedRooms(
        string $sessionType,
        int $groupSize,
        string $dayOfWeek,
        string $startTime,
        string $endTime,
        int $semesterId,
        ?int $excludeSlotId = null
    ): Collection {
        $allowedTypes = self::TYPE_MAPPING[$sessionType] ?? ['Salle'];

        $rooms = Room::query()
            ->active()
            ->whereIn('type', $allowedTypes)
            ->where('capacity', '>=', $groupSize)
            ->availableForSlot($dayOfWeek, $startTime, $endTime, $semesterId, $excludeSlotId)
            ->orderBy('capacity', 'asc')
            ->get();

        return $rooms->map(function ($room) use ($groupSize) {
            return [
                'room' => $room,
                'capacity_match' => $room->capacity >= $groupSize,
                'capacity_warning' => $room->capacity < $groupSize * 1.1,
                'excess_capacity' => $room->capacity - $groupSize,
            ];
        });
    }

    /**
     * Génère un rapport d'occupation pour une salle
     */
    public function getOccupationReport(Room $room, int $semesterId): array
    {
        $slots = TimetableSlot::query()
            ->where('room_id', $room->id)
            ->where('semester_id', $semesterId)
            ->with(['module', 'teacher', 'group'])
            ->get()
            ->groupBy('day_of_week');

        $days = TimetableSlot::VALID_DAYS;
        $report = [];
        $totalHoursOccupied = 0;

        foreach ($days as $day) {
            $daySlots = $slots->get($day, collect());
            $hoursOccupied = $daySlots->sum(function ($slot) {
                $start = Carbon::createFromFormat('H:i:s', $slot->start_time);
                $end = Carbon::createFromFormat('H:i:s', $slot->end_time);

                return $end->diffInMinutes($start) / 60;
            });

            $totalHoursOccupied += $hoursOccupied;

            $report[$day] = [
                'slots_count' => $daySlots->count(),
                'hours_occupied' => round($hoursOccupied, 1),
                'slots' => $daySlots->map(fn ($s) => [
                    'id' => $s->id,
                    'time_range' => $s->time_range,
                    'type' => $s->type,
                    'module' => $s->module?->name,
                    'teacher' => $s->teacher?->name ?? ($s->teacher?->firstname.' '.$s->teacher?->lastname),
                    'group' => $s->group?->name,
                ])->values(),
            ];
        }

        // Calculer le taux d'occupation (6 jours * 10h = 60h max/semaine)
        $maxHoursPerWeek = 60;
        $occupationRate = $maxHoursPerWeek > 0 ? round(($totalHoursOccupied / $maxHoursPerWeek) * 100, 2) : 0;

        return [
            'room' => [
                'id' => $room->id,
                'code' => $room->code,
                'name' => $room->name,
                'type' => $room->type,
                'capacity' => $room->capacity,
            ],
            'semester_id' => $semesterId,
            'summary' => [
                'total_slots' => $slots->flatten()->count(),
                'total_hours_per_week' => round($totalHoursOccupied, 1),
                'occupation_rate' => $occupationRate,
                'status' => $this->getOccupationStatus($occupationRate),
            ],
            'by_day' => $report,
        ];
    }

    /**
     * Obtient le statut d'occupation basé sur le taux
     */
    private function getOccupationStatus(float $rate): string
    {
        if ($rate < 30) {
            return 'under_used';
        }
        if ($rate < 70) {
            return 'normal';
        }
        if ($rate < 90) {
            return 'high_usage';
        }

        return 'over_used';
    }

    /**
     * Bloque une salle pour maintenance
     */
    public function blockRoom(Room $room, string $reason, Carbon $from, Carbon $to): array
    {
        // Trouver les séances impactées
        $impactedSlots = $this->findImpactedSlots($room, $from, $to);

        $room->update([
            'is_active' => false,
            'unavailable_reason' => $reason,
            'unavailable_from' => $from,
            'unavailable_to' => $to,
        ]);

        return [
            'room' => $room,
            'blocked_from' => $from->toDateTimeString(),
            'blocked_to' => $to->toDateTimeString(),
            'impacted_slots_count' => $impactedSlots->count(),
            'impacted_slots' => $impactedSlots->map(fn ($slot) => [
                'id' => $slot->id,
                'day' => $slot->day_of_week,
                'time' => $slot->time_range,
                'module' => $slot->module?->name,
                'teacher' => $slot->teacher?->name ?? ($slot->teacher?->firstname.' '.$slot->teacher?->lastname),
            ]),
        ];
    }

    /**
     * Débloque une salle
     */
    public function unblockRoom(Room $room): Room
    {
        $room->update([
            'is_active' => true,
            'unavailable_reason' => null,
            'unavailable_from' => null,
            'unavailable_to' => null,
        ]);

        return $room;
    }

    /**
     * Trouve les séances impactées par le blocage d'une salle
     */
    private function findImpactedSlots(Room $room, Carbon $from, Carbon $to): Collection
    {
        return TimetableSlot::query()
            ->where('room_id', $room->id)
            ->whereHas('semester', function ($q) use ($from, $to) {
                $q->where('start_date', '<=', $to)
                    ->where('end_date', '>=', $from);
            })
            ->with(['module', 'teacher', 'group'])
            ->get();
    }

    /**
     * Obtient les statistiques d'occupation pour toutes les salles
     */
    public function getGlobalOccupationStats(int $semesterId): array
    {
        $rooms = Room::query()->active()->get();

        $stats = [
            'under_used' => [],
            'normal' => [],
            'high_usage' => [],
            'over_used' => [],
        ];

        foreach ($rooms as $room) {
            $occupation = $room->getOccupationForSemester($semesterId);
            $status = $this->getOccupationStatus($occupation['occupation_rate']);

            $stats[$status][] = [
                'room' => $room,
                'occupation_rate' => $occupation['occupation_rate'],
                'total_hours' => $occupation['total_hours_per_week'],
            ];
        }

        return [
            'semester_id' => $semesterId,
            'total_rooms' => $rooms->count(),
            'under_used_count' => count($stats['under_used']),
            'normal_count' => count($stats['normal']),
            'high_usage_count' => count($stats['high_usage']),
            'over_used_count' => count($stats['over_used']),
            'details' => $stats,
        ];
    }
}
