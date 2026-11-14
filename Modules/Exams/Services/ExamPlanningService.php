<?php

namespace Modules\Exams\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Exams\Entities\ExamRoomAssignment;
use Modules\Exams\Entities\ExamSession;
use Modules\StructureAcademique\Entities\Classe;
use Modules\StructureAcademique\Entities\Semester;
use Modules\Timetable\Entities\Room;

/**
 * Service for Exam Planning (Epic 1: Stories 01-04)
 * - Story 01: Create exam sessions
 * - Story 02: Assign rooms to exam sessions
 * - Story 03: Validate exam schedules (detect conflicts)
 * - Story 04: Duplicate exam sessions
 */
class ExamPlanningService
{
    /**
     * Story 01: Create an exam session
     */
    public function createExamSession(array $data): ExamSession
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $data['created_by'] ??= auth('tenant')->id();

            // Calculate duration in minutes
            if (! isset($data['duration_minutes'])) {
                $start = Carbon::parse($data['start_time']);
                $end = Carbon::parse($data['end_time']);
                $data['duration_minutes'] = $start->diffInMinutes($end);
            }

            $roomIds = $data['room_ids'] ?? [];
            unset($data['room_ids']);

            $data = $this->resolveLyceeTarget($data);

            $session = ExamSession::create($data);

            if (! empty($roomIds)) {
                $this->assignMultipleRooms($session, array_map(fn ($roomId): array => ['room_id' => (int) $roomId], $roomIds));
            }

            DB::connection('tenant')->commit();

            return $session->fresh();
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            throw $e;
        }
    }

    /**
     * Complète la cible lycée d'une session : une session de classe hérite du niveau et de
     * l'année de la classe ; le semestre est déduit de la date de l'épreuve s'il n'est pas fourni.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resolveLyceeTarget(array $data): array
    {
        if (! ExamSession::hasLyceeColumns()) {
            return $data;
        }

        if (! empty($data['classe_id'])) {
            $classe = Classe::query()->findOrFail($data['classe_id']);
            $data['level_id'] = $classe->level_id;
            $data['academic_year_id'] = $classe->academic_year_id;
        }

        if (empty($data['semester_id']) && ! empty($data['academic_year_id']) && ! empty($data['exam_date'])) {
            $data['semester_id'] = $this->findSemesterForDate((int) $data['academic_year_id'], (string) $data['exam_date'])?->id;
        }

        return $data;
    }

    /**
     * Semestre de l'année scolaire contenant la date donnée.
     */
    public function findSemesterForDate(int $academicYearId, string $date): ?Semester
    {
        $day = Carbon::parse($date)->toDateString();

        return Semester::query()
            ->where('academic_year_id', $academicYearId)
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            ->first();
    }

    /**
     * Story 01: Update an exam session
     */
    public function updateExamSession(ExamSession $session, array $data): ExamSession
    {
        DB::connection('tenant')->beginTransaction();

        try {
            // Recalculate duration if times changed
            if (isset($data['start_time']) || isset($data['end_time'])) {
                $start = Carbon::parse($data['start_time'] ?? $session->start_time);
                $end = Carbon::parse($data['end_time'] ?? $session->end_time);
                $data['duration_minutes'] = $start->diffInMinutes($end);
            }

            if (array_key_exists('classe_id', $data) || array_key_exists('level_id', $data)) {
                if (! empty($data['classe_id'])) {
                    $data['level_id'] = null;
                } elseif (! empty($data['level_id'])) {
                    $data['classe_id'] = null;
                }

                $data['academic_year_id'] ??= $session->academic_year_id;
            }

            if (isset($data['exam_date']) && ! array_key_exists('semester_id', $data)) {
                $data['semester_id'] = null;
                $data['academic_year_id'] ??= $session->academic_year_id;
            }

            $session->update($this->resolveLyceeTarget($data));

            DB::connection('tenant')->commit();

            return $session->fresh();
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            throw $e;
        }
    }

    /**
     * Story 02: Assign a room to an exam session
     */
    public function assignRoom(ExamSession $session, int $roomId, array $data = []): ExamRoomAssignment
    {
        $room = Room::findOrFail($roomId);

        $assignmentData = array_merge([
            'exam_session_id' => $session->id,
            'room_id' => $roomId,
            'capacity' => $data['capacity'] ?? $room->capacity,
            'assigned_students' => 0,
        ], $data);

        return ExamRoomAssignment::create($assignmentData);
    }

    /**
     * Story 02: Assign multiple rooms to an exam session
     */
    public function assignMultipleRooms(ExamSession $session, array $rooms): Collection
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $assignments = collect();

            foreach ($rooms as $roomData) {
                $assignment = $this->assignRoom($session, $roomData['room_id'], $roomData);
                $assignments->push($assignment);
            }

            // Update total capacity
            $totalCapacity = $assignments->sum('capacity');
            $session->update(['total_capacity' => $totalCapacity]);

            DB::connection('tenant')->commit();

            return $assignments;
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            throw $e;
        }
    }

    /**
     * Story 03: Validate exam schedule (detect conflicts)
     * Returns array of conflicts or empty array if no conflicts
     */
    public function validateSchedule(array $data, ?int $excludeSessionId = null): array
    {
        $conflicts = [];

        $examDate = $data['exam_date'];
        $startTime = $data['start_time'];
        $endTime = $data['end_time'];

        // Check for room conflicts if rooms are specified
        if (isset($data['room_ids']) && is_array($data['room_ids'])) {
            $conflicts = array_merge($conflicts, $this->checkRoomConflicts(
                $data['room_ids'],
                $examDate,
                $startTime,
                $endTime,
                $excludeSessionId
            ));
        }

        // Lycée : une classe ne peut pas composer deux épreuves en même temps
        if (! empty($data['classe_id']) || ! empty($data['level_id'])) {
            $conflicts = array_merge($conflicts, $this->checkClassConflicts($data, $excludeSessionId));
        }

        // Check for module conflicts (same module at same time)
        if (isset($data['module_id'])) {
            $moduleConflicts = $this->checkModuleConflicts(
                $data['module_id'],
                $examDate,
                $startTime,
                $endTime,
                $excludeSessionId
            );

            if ($moduleConflicts) {
                $conflicts[] = $moduleConflicts;
            }
        }

        return $conflicts;
    }

    /**
     * Story 03: Check room availability conflicts
     */
    private function checkRoomConflicts(
        array $roomIds,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeSessionId = null
    ): array {
        $conflicts = [];

        foreach ($roomIds as $roomId) {
            $query = ExamRoomAssignment::query()
                ->where('room_id', $roomId)
                ->whereHas('examSession', function ($q) use ($date, $startTime, $endTime, $excludeSessionId) {
                    $q->where('exam_date', $date)
                        ->where('status', '!=', 'cancelled')
                        ->where(function ($timeQuery) use ($startTime, $endTime) {
                            $timeQuery->whereBetween('start_time', [$startTime, $endTime])
                                ->orWhereBetween('end_time', [$startTime, $endTime])
                                ->orWhere(function ($between) use ($startTime, $endTime) {
                                    $between->where('start_time', '<=', $startTime)
                                        ->where('end_time', '>=', $endTime);
                                });
                        });

                    if ($excludeSessionId) {
                        $q->where('id', '!=', $excludeSessionId);
                    }
                })
                ->with(['examSession', 'room'])
                ->get();

            if ($query->isNotEmpty()) {
                foreach ($query as $assignment) {
                    $conflicts[] = [
                        'type' => 'room_conflict',
                        'room_id' => $roomId,
                        'room_name' => $assignment->room->name,
                        'conflicting_session' => $assignment->examSession->title,
                        'date' => $assignment->examSession->exam_date,
                        'time' => $assignment->examSession->start_time.' - '.$assignment->examSession->end_time,
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Story 03 (lycée) : sessions qui se chevauchent pour la même classe ou le même niveau.
     * Une session de classe porte aussi le level_id de sa classe : comparer le niveau
     * couvre donc les épreuves de niveau et celles des autres classes du niveau ;
     * pour une session de classe, seules la même classe et les épreuves de niveau sont en conflit.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function checkClassConflicts(array $data, ?int $excludeSessionId = null): array
    {
        if (! ExamSession::hasLyceeColumns()) {
            return [];
        }

        $classeId = $data['classe_id'] ?? null;
        $levelId = $data['level_id'] ?? null;
        $academicYearId = $data['academic_year_id'] ?? null;

        if ($classeId) {
            $classe = Classe::query()->find($classeId);
            $levelId = $classe?->level_id;
            $academicYearId = $classe?->academic_year_id;
        }

        $query = ExamSession::query()
            ->with(['classe', 'level', 'subject'])
            ->whereDate('exam_date', Carbon::parse($data['exam_date'])->toDateString())
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->where(function ($target) use ($classeId, $levelId, $academicYearId) {
                if ($classeId) {
                    $target->where('classe_id', $classeId);
                }

                if ($levelId) {
                    $method = $classeId ? 'orWhere' : 'where';
                    $target->{$method}(function ($levelQuery) use ($classeId, $levelId, $academicYearId) {
                        $levelQuery->where('level_id', $levelId);

                        if ($academicYearId) {
                            $levelQuery->where('academic_year_id', $academicYearId);
                        }

                        if ($classeId) {
                            $levelQuery->whereNull('classe_id');
                        }
                    });
                }
            });

        if ($excludeSessionId) {
            $query->where('id', '!=', $excludeSessionId);
        }

        return $query->get()->map(fn (ExamSession $session): array => [
            'type' => 'class_conflict',
            'classe' => $session->classe?->name ?? $session->level?->name,
            'subject' => $session->subject?->name,
            'conflicting_session' => $session->title,
            'date' => $session->exam_date?->toDateString(),
            'time' => $session->getRawOriginal('start_time').' - '.$session->getRawOriginal('end_time'),
        ])->values()->all();
    }

    /**
     * Story 03: Check module conflicts (same module scheduled multiple times)
     */
    private function checkModuleConflicts(
        int $moduleId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeSessionId = null
    ): ?array {
        $query = ExamSession::query()
            ->where('module_id', $moduleId)
            ->where('exam_date', $date)
            ->where('status', '!=', 'cancelled');

        if ($excludeSessionId) {
            $query->where('id', '!=', $excludeSessionId);
        }

        $existingSession = $query->first();

        if ($existingSession) {
            return [
                'type' => 'module_conflict',
                'module_id' => $moduleId,
                'conflicting_session' => $existingSession->title,
                'date' => $existingSession->exam_date,
                'time' => $existingSession->start_time.' - '.$existingSession->end_time,
            ];
        }

        return null;
    }

    /**
     * Story 04: Duplicate an exam session
     */
    public function duplicateSession(ExamSession $session, array $overrides = []): ExamSession
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $newSessionData = array_merge(
                $session->only(array_merge([
                    'module_id',
                    'evaluation_period_id',
                    'academic_year_id',
                    'type',
                    'duration_minutes',
                    'instructions',
                    'allowed_materials',
                ], ExamSession::hasLyceeColumns() ? ['subject_id', 'classe_id', 'level_id', 'semester_id'] : [])),
                [
                    'title' => $overrides['title'] ?? $session->title.' (Copie)',
                    'description' => $overrides['description'] ?? $session->description,
                    'exam_date' => $overrides['exam_date'] ?? $session->exam_date,
                    'start_time' => $overrides['start_time'] ?? $session->start_time,
                    'end_time' => $overrides['end_time'] ?? $session->end_time,
                    'status' => 'draft',
                    'is_published' => false,
                    'published_at' => null,
                    'created_by' => auth('tenant')->id(),
                ]
            );

            $newSession = ExamSession::create($newSessionData);

            // Copy room assignments if requested
            if ($overrides['copy_rooms'] ?? true) {
                foreach ($session->roomAssignments as $assignment) {
                    $this->assignRoom($newSession, $assignment->room_id, [
                        'capacity' => $assignment->capacity,
                        'seat_start_number' => $assignment->seat_start_number,
                        'seat_end_number' => $assignment->seat_end_number,
                    ]);
                }
            }

            DB::connection('tenant')->commit();

            return $newSession->load('roomAssignments');
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            throw $e;
        }
    }

    /**
     * Story 01: Publish an exam session
     */
    public function publishSession(ExamSession $session): ExamSession
    {
        $session->update([
            'is_published' => true,
            'published_at' => now(),
            'status' => 'planned',
        ]);

        return $session;
    }

    /**
     * Story 01: Cancel an exam session
     */
    public function cancelSession(ExamSession $session, ?string $reason = null): ExamSession
    {
        $attributes = ['status' => 'cancelled'];

        if (ExamSession::hasLyceeColumns()) {
            $attributes['cancellation_reason'] = $reason;
        }

        $session->update($attributes);

        return $session;
    }

    /**
     * Passage d'une session au statut « en cours » ou « terminée ».
     */
    public function changeStatus(ExamSession $session, string $status): ExamSession
    {
        $session->update(['status' => $status]);

        return $session;
    }

    /**
     * Get available rooms for a specific date/time
     */
    public function getAvailableRooms(string $date, string $startTime, string $endTime): Collection
    {
        $bookedRoomIds = ExamRoomAssignment::query()
            ->whereHas('examSession', function ($q) use ($date, $startTime, $endTime) {
                $q->where('exam_date', $date)
                    ->where('status', '!=', 'cancelled')
                    ->where(function ($timeQuery) use ($startTime, $endTime) {
                        $timeQuery->whereBetween('start_time', [$startTime, $endTime])
                            ->orWhereBetween('end_time', [$startTime, $endTime])
                            ->orWhere(function ($between) use ($startTime, $endTime) {
                                $between->where('start_time', '<=', $startTime)
                                    ->where('end_time', '>=', $endTime);
                            });
                    });
            })
            ->pluck('room_id')
            ->unique();

        return Room::query()
            ->whereNotIn('id', $bookedRoomIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
