<?php

namespace Modules\Timetable\Services;

use Illuminate\Support\Facades\DB;
use Modules\StructureAcademique\Entities\Group;
use Modules\Timetable\DTOs\DuplicationResult;
use Modules\Timetable\Entities\Room;
use Modules\Timetable\Entities\TimetableSlot;
use Modules\Timetable\Exceptions\DuplicationException;

class TimetableDuplicationService
{
    public function __construct(
        private ConflictDetectionService $conflictDetector
    ) {}

    /**
     * Dupliquer emploi du temps d'un semestre vers un autre
     */
    public function duplicate(
        int $sourceSemesterId,
        int $sourceGroupId,
        int $targetSemesterId,
        int $targetGroupId,
        array $options = []
    ): DuplicationResult {
        // Récupérer slots source
        $sourceSlots = TimetableSlot::with(['module', 'teacher', 'room'])
            ->where('semester_id', $sourceSemesterId)
            ->where('group_id', $sourceGroupId)
            ->get();

        if ($sourceSlots->isEmpty()) {
            throw new DuplicationException('Aucun emploi du temps trouvé pour le groupe et semestre source.');
        }

        // Vérifier si cible existe déjà
        $existingSlots = TimetableSlot::where('semester_id', $targetSemesterId)
            ->where('group_id', $targetGroupId)
            ->count();

        if ($existingSlots > 0 && ! ($options['force'] ?? false)) {
            throw new DuplicationException('Un emploi du temps existe déjà pour ce groupe et semestre. Utilisez force=true pour écraser.');
        }

        $newSlots = [];
        $report = [
            'total_slots' => $sourceSlots->count(),
            'duplicated_successfully' => 0,
            'missing_teachers' => [],
            'missing_rooms' => [],
            'conflicts' => [],
        ];

        DB::connection('tenant')->transaction(function () use (
            $sourceSlots,
            $targetSemesterId,
            $targetGroupId,
            $options,
            &$newSlots,
            &$report
        ) {
            // Supprimer slots existants si force=true
            if ($options['force'] ?? false) {
                TimetableSlot::where('semester_id', $targetSemesterId)
                    ->where('group_id', $targetGroupId)
                    ->delete();
            }

            foreach ($sourceSlots as $sourceSlot) {
                // Filtrage si mode sélectif
                if (! empty($options['selected_modules']) &&
                    ! in_array($sourceSlot->module_id, $options['selected_modules'])) {
                    continue;
                }

                $slotData = [
                    'module_id' => $sourceSlot->module_id,
                    'semester_id' => $targetSemesterId,
                    'group_id' => $targetGroupId,
                    'day_of_week' => $sourceSlot->day_of_week,
                    'start_time' => $sourceSlot->start_time,
                    'end_time' => $sourceSlot->end_time,
                    'type' => $sourceSlot->type,
                    'notes' => $sourceSlot->notes,
                ];

                // Gestion enseignant
                $mode = $options['mode'] ?? 'full';
                if ($mode === 'full' && $sourceSlot->teacher_id) {
                    $teacherStillAssigned = $this->isTeacherStillAssigned(
                        $sourceSlot->teacher_id,
                        $sourceSlot->module_id,
                        $targetSemesterId
                    );

                    if ($teacherStillAssigned) {
                        $slotData['teacher_id'] = $sourceSlot->teacher_id;
                    } else {
                        $slotData['teacher_id'] = null;
                        $report['missing_teachers'][] = [
                            'module' => $sourceSlot->module->name ?? 'N/A',
                            'day' => $sourceSlot->day_of_week,
                            'time' => $sourceSlot->start_time,
                            'original_teacher' => $sourceSlot->teacher->name ?? 'N/A',
                        ];
                    }
                } else {
                    $slotData['teacher_id'] = null;
                }

                // Gestion salle
                if (($options['duplicate_rooms'] ?? true) && $sourceSlot->room_id) {
                    $room = Room::find($sourceSlot->room_id);
                    $roomAvailable = $room && $room->is_active;

                    if ($roomAvailable) {
                        $slotData['room_id'] = $sourceSlot->room_id;
                    } else {
                        $slotData['room_id'] = null;
                        $report['missing_rooms'][] = [
                            'module' => $sourceSlot->module->name ?? 'N/A',
                            'day' => $sourceSlot->day_of_week,
                            'time' => $sourceSlot->start_time,
                            'original_room' => $room->name ?? 'N/A',
                        ];
                    }
                } else {
                    $slotData['room_id'] = null;
                }

                $newSlot = TimetableSlot::create($slotData);
                $newSlots[] = $newSlot;

                // Vérifier conflits
                $conflictResult = $this->conflictDetector->detectConflicts($newSlot, $newSlot->id);
                if ($conflictResult['hasConflicts']) {
                    $report['conflicts'][] = [
                        'slot_id' => $newSlot->id,
                        'module' => $newSlot->module->name ?? 'N/A',
                        'conflicts' => $conflictResult['conflicts'],
                    ];
                } else {
                    $report['duplicated_successfully']++;
                }
            }
        });

        return new DuplicationResult(
            success: true,
            newSlots: array_map(fn ($slot) => $slot->id, $newSlots),
            report: $report,
            message: sprintf(
                '%d/%d séances dupliquées avec succès',
                $report['duplicated_successfully'],
                $report['total_slots']
            )
        );
    }

    /**
     * Obtenir aperçu avant duplication
     */
    public function getPreview(int $sourceSemesterId, int $sourceGroupId): array
    {
        $slots = TimetableSlot::with(['module', 'teacher', 'room'])
            ->where('semester_id', $sourceSemesterId)
            ->where('group_id', $sourceGroupId)
            ->get();

        return [
            'total_slots' => $slots->count(),
            'modules' => $slots->pluck('module.name')->unique()->values(),
            'teachers' => $slots->pluck('teacher.name')->filter()->unique()->values(),
            'rooms' => $slots->pluck('room.name')->filter()->unique()->values(),
            'days_covered' => $slots->pluck('day_of_week')->unique()->values(),
        ];
    }

    /**
     * Obtenir suggestions pour une séance
     */
    public function getSuggestions(TimetableSlot $slot): array
    {
        $suggestions = [
            'teachers' => [],
            'rooms' => [],
        ];

        // Enseignants disponibles pour ce module
        if (! $slot->teacher_id) {
            $teachers = DB::connection('tenant')
                ->table('module_semester_assignment')
                ->join('users', 'users.id', '=', 'module_semester_assignment.teacher_id')
                ->where('module_semester_assignment.module_id', $slot->module_id)
                ->where('module_semester_assignment.semester_id', $slot->semester_id)
                ->select('users.id', 'users.name')
                ->get();

            foreach ($teachers as $teacher) {
                $isAvailable = ! $this->hasTeacherConflict($teacher->id, $slot);
                $suggestions['teachers'][] = [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'is_available' => $isAvailable,
                ];
            }
        }

        // Salles compatibles disponibles
        if (! $slot->room_id) {
            $group = Group::with('students')->find($slot->group_id);
            $groupSize = $group->students->count();
            $roomType = $this->getRoomTypeForSession($slot->type);

            $rooms = Room::where('is_active', true)
                ->where('type', $roomType)
                ->where('capacity', '>=', $groupSize)
                ->get();

            foreach ($rooms as $room) {
                $isAvailable = ! $this->hasRoomConflict($room->id, $slot);
                $suggestions['rooms'][] = [
                    'id' => $room->id,
                    'name' => $room->name,
                    'capacity' => $room->capacity,
                    'type' => $room->type,
                    'is_available' => $isAvailable,
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Vérifier si enseignant est toujours affecté au module
     */
    private function isTeacherStillAssigned(int $teacherId, int $moduleId, int $semesterId): bool
    {
        return DB::connection('tenant')
            ->table('module_semester_assignment')
            ->where('teacher_id', $teacherId)
            ->where('module_id', $moduleId)
            ->where('semester_id', $semesterId)
            ->exists();
    }

    /**
     * Vérifier conflit enseignant
     */
    private function hasTeacherConflict(int $teacherId, TimetableSlot $slot): bool
    {
        return TimetableSlot::where('teacher_id', $teacherId)
            ->where('semester_id', $slot->semester_id)
            ->where('day_of_week', $slot->day_of_week)
            ->where('id', '!=', $slot->id)
            ->where(function ($query) use ($slot) {
                $query->whereBetween('start_time', [$slot->start_time, $slot->end_time])
                    ->orWhereBetween('end_time', [$slot->start_time, $slot->end_time])
                    ->orWhere(function ($q) use ($slot) {
                        $q->where('start_time', '<=', $slot->start_time)
                            ->where('end_time', '>=', $slot->end_time);
                    });
            })
            ->exists();
    }

    /**
     * Vérifier conflit salle
     */
    private function hasRoomConflict(int $roomId, TimetableSlot $slot): bool
    {
        return TimetableSlot::where('room_id', $roomId)
            ->where('semester_id', $slot->semester_id)
            ->where('day_of_week', $slot->day_of_week)
            ->where('id', '!=', $slot->id)
            ->where(function ($query) use ($slot) {
                $query->whereBetween('start_time', [$slot->start_time, $slot->end_time])
                    ->orWhereBetween('end_time', [$slot->start_time, $slot->end_time])
                    ->orWhere(function ($q) use ($slot) {
                        $q->where('start_time', '<=', $slot->start_time)
                            ->where('end_time', '>=', $slot->end_time);
                    });
            })
            ->exists();
    }

    /**
     * Obtenir type de salle recommandé selon type de séance
     */
    private function getRoomTypeForSession(string $sessionType): string
    {
        return match ($sessionType) {
            'CM' => 'Amphithéâtre',
            'TD' => 'Salle TD',
            'TP' => 'Laboratoire Informatique',
            default => 'Salle TD',
        };
    }
}
