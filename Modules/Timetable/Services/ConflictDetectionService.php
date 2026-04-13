<?php

namespace Modules\Timetable\Services;

use Illuminate\Support\Collection;
use Modules\Timetable\Entities\TimetableSlot;

class ConflictDetectionService
{
    /**
     * Résultat de la détection de conflits
     *
     * @var array{hasConflicts: bool, conflicts: array, warnings: array}
     */
    private array $result = [
        'hasConflicts' => false,
        'conflicts' => [],
        'warnings' => [],
    ];

    /**
     * Détecte tous les conflits potentiels pour un créneau donné
     */
    public function detectConflicts(TimetableSlot $slot, ?int $excludeId = null): array
    {
        $this->result = [
            'hasConflicts' => false,
            'conflicts' => [],
            'warnings' => [],
        ];

        // Vérification des conflits bloquants
        $this->checkTeacherConflict($slot, $excludeId);
        $this->checkRoomConflict($slot, $excludeId);
        $this->checkGroupConflict($slot, $excludeId);

        // Vérifications de type warning
        $this->checkRoomCapacity($slot);
        $this->checkRoomSuitability($slot);

        return $this->result;
    }

    /**
     * Vérifie les conflits d'enseignant
     */
    private function checkTeacherConflict(TimetableSlot $slot, ?int $excludeId = null): void
    {
        $query = TimetableSlot::query()
            ->where('teacher_id', $slot->teacher_id)
            ->where('semester_id', $slot->semester_id)
            ->where('day_of_week', $slot->day_of_week)
            ->overlapping($slot->day_of_week, $slot->start_time, $slot->end_time, $slot->semester_id);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $conflictingSlots = $query->with(['module', 'group', 'room'])->get();

        foreach ($conflictingSlots as $conflictingSlot) {
            $this->result['hasConflicts'] = true;
            $this->result['conflicts'][] = [
                'type' => 'teacher',
                'severity' => 'error',
                'message' => "L'enseignant est déjà occupé le {$conflictingSlot->day_of_week} de {$conflictingSlot->time_range}",
                'details' => [
                    'conflicting_slot_id' => $conflictingSlot->id,
                    'module' => $conflictingSlot->module?->name,
                    'group' => $conflictingSlot->group?->name,
                    'room' => $conflictingSlot->room?->name,
                    'time_range' => $conflictingSlot->time_range,
                ],
            ];
        }
    }

    /**
     * Vérifie les conflits de salle
     */
    private function checkRoomConflict(TimetableSlot $slot, ?int $excludeId = null): void
    {
        $query = TimetableSlot::query()
            ->where('room_id', $slot->room_id)
            ->where('semester_id', $slot->semester_id)
            ->where('day_of_week', $slot->day_of_week)
            ->overlapping($slot->day_of_week, $slot->start_time, $slot->end_time, $slot->semester_id);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $conflictingSlots = $query->with(['module', 'teacher', 'group'])->get();

        foreach ($conflictingSlots as $conflictingSlot) {
            $this->result['hasConflicts'] = true;
            $this->result['conflicts'][] = [
                'type' => 'room',
                'severity' => 'error',
                'message' => "La salle est déjà occupée le {$conflictingSlot->day_of_week} de {$conflictingSlot->time_range}",
                'details' => [
                    'conflicting_slot_id' => $conflictingSlot->id,
                    'module' => $conflictingSlot->module?->name,
                    'teacher' => $conflictingSlot->teacher?->name ?? 'N/A',
                    'group' => $conflictingSlot->group?->name,
                    'time_range' => $conflictingSlot->time_range,
                ],
            ];
        }
    }

    /**
     * Vérifie les conflits de groupe
     */
    private function checkGroupConflict(TimetableSlot $slot, ?int $excludeId = null): void
    {
        $query = TimetableSlot::query()
            ->where('group_id', $slot->group_id)
            ->where('semester_id', $slot->semester_id)
            ->where('day_of_week', $slot->day_of_week)
            ->overlapping($slot->day_of_week, $slot->start_time, $slot->end_time, $slot->semester_id);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $conflictingSlots = $query->with(['module', 'teacher', 'room'])->get();

        foreach ($conflictingSlots as $conflictingSlot) {
            $this->result['hasConflicts'] = true;
            $this->result['conflicts'][] = [
                'type' => 'group',
                'severity' => 'error',
                'message' => "Le groupe a déjà un cours le {$conflictingSlot->day_of_week} de {$conflictingSlot->time_range}",
                'details' => [
                    'conflicting_slot_id' => $conflictingSlot->id,
                    'module' => $conflictingSlot->module?->name,
                    'teacher' => $conflictingSlot->teacher?->name ?? 'N/A',
                    'room' => $conflictingSlot->room?->name,
                    'time_range' => $conflictingSlot->time_range,
                ],
            ];
        }
    }

    /**
     * Vérifie si la capacité de la salle est suffisante
     */
    private function checkRoomCapacity(TimetableSlot $slot): void
    {
        $room = $slot->room;
        $group = $slot->group;

        if (! $room || ! $group) {
            return;
        }

        $groupSize = $group->current_count ?? $group->capacity_max ?? 0;

        if ($room->capacity < $groupSize) {
            $this->result['warnings'][] = [
                'type' => 'capacity',
                'severity' => 'warning',
                'message' => "La capacité de la salle ({$room->capacity}) est inférieure à l'effectif du groupe ({$groupSize})",
                'details' => [
                    'room_capacity' => $room->capacity,
                    'group_size' => $groupSize,
                    'overflow' => $groupSize - $room->capacity,
                ],
            ];
        }
    }

    /**
     * Vérifie si le type de salle est adapté au type de séance
     */
    private function checkRoomSuitability(TimetableSlot $slot): void
    {
        $room = $slot->room;

        if (! $room) {
            return;
        }

        if (! $room->isSuitableForType($slot->type)) {
            $this->result['warnings'][] = [
                'type' => 'suitability',
                'severity' => 'warning',
                'message' => "Le type de salle ({$room->type}) n'est pas idéal pour une séance de type {$slot->type}",
                'details' => [
                    'room_type' => $room->type,
                    'session_type' => $slot->type,
                    'recommended_types' => $this->getRecommendedRoomTypes($slot->type),
                ],
            ];
        }
    }

    /**
     * Retourne les types de salles recommandés pour un type de séance
     */
    private function getRecommendedRoomTypes(string $sessionType): array
    {
        return match ($sessionType) {
            'CM' => ['Amphi', 'Salle'],
            'TD' => ['Salle', 'Amphi'],
            'TP' => ['Labo', 'Salle_Info'],
            default => ['Salle'],
        };
    }

    /**
     * Suggère des créneaux alternatifs disponibles
     */
    public function suggestAlternatives(TimetableSlot $slot, int $limit = 10): Collection
    {
        $alternatives = collect();

        $days = TimetableSlot::VALID_DAYS;
        $timeSlots = TimetableSlot::STANDARD_SLOTS;

        foreach ($days as $day) {
            foreach ($timeSlots as [$start, $end]) {
                // Créer un créneau de test
                $testSlot = new TimetableSlot([
                    'module_id' => $slot->module_id,
                    'teacher_id' => $slot->teacher_id,
                    'group_id' => $slot->group_id,
                    'room_id' => $slot->room_id,
                    'semester_id' => $slot->semester_id,
                    'day_of_week' => $day,
                    'start_time' => $start.':00',
                    'end_time' => $end.':00',
                    'type' => $slot->type,
                ]);

                // Charger les relations nécessaires pour la vérification
                $testSlot->setRelation('room', $slot->room);
                $testSlot->setRelation('group', $slot->group);

                $result = $this->detectConflicts($testSlot);

                if (! $result['hasConflicts']) {
                    $alternatives->push([
                        'day_of_week' => $day,
                        'start_time' => $start,
                        'end_time' => $end,
                        'warnings' => $result['warnings'],
                    ]);

                    if ($alternatives->count() >= $limit) {
                        return $alternatives;
                    }
                }
            }
        }

        return $alternatives;
    }

    /**
     * Vérifie si un créneau spécifique est disponible
     */
    public function isSlotAvailable(TimetableSlot $slot, ?int $excludeId = null): bool
    {
        $result = $this->detectConflicts($slot, $excludeId);

        return ! $result['hasConflicts'];
    }

    /**
     * Vérifie les conflits pour une liste de créneaux (bulk check)
     */
    public function detectBulkConflicts(array $slots): array
    {
        $results = [];

        foreach ($slots as $index => $slotData) {
            $slot = new TimetableSlot($slotData);

            // Charger les relations si nécessaires
            if (isset($slotData['room'])) {
                $slot->setRelation('room', $slotData['room']);
            }
            if (isset($slotData['group'])) {
                $slot->setRelation('group', $slotData['group']);
            }

            $results[$index] = $this->detectConflicts($slot);
        }

        return $results;
    }
}
