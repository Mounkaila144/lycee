<?php

namespace Modules\Exams\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Exams\Entities\ExamRoomAssignment;
use Modules\Exams\Entities\ExamSession;
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
            // Calculate duration in minutes
            if (! isset($data['duration_minutes'])) {
                $start = Carbon::parse($data['start_time']);
                $end = Carbon::parse($data['end_time']);
                $data['duration_minutes'] = $start->diffInMinutes($end);
            }

            $session = ExamSession::create($data);

            DB::connection('tenant')->commit();

            return $session;
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            throw $e;
        }
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

            $session->update($data);

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
                $session->only([
                    'module_id',
                    'evaluation_period_id',
                    'academic_year_id',
                    'type',
                    'duration_minutes',
                    'instructions',
                    'allowed_materials',
                ]),
                [
                    'title' => $overrides['title'] ?? $session->title.' (Copie)',
                    'description' => $overrides['description'] ?? $session->description,
                    'exam_date' => $overrides['exam_date'] ?? $session->exam_date,
                    'start_time' => $overrides['start_time'] ?? $session->start_time,
                    'end_time' => $overrides['end_time'] ?? $session->end_time,
                    'status' => 'draft',
                    'is_published' => false,
                    'published_at' => null,
                    'created_by' => auth()->id(),
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
        $session->update([
            'status' => 'cancelled',
            'notes' => $reason,
        ]);

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
