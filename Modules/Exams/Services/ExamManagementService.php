<?php

namespace Modules\Exams\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\Student;
use Modules\Exams\Entities\ExamAttendanceSheet;
use Modules\Exams\Entities\ExamRoomAssignment;
use Modules\Exams\Entities\ExamSession;

/**
 * Service for Exam Management (Epic 2: Stories 05-07)
 * - Story 05: Manage exam papers and documents
 * - Story 06: Assign students to exam rooms
 * - Story 07: Exam preparation and materials checklist
 */
class ExamManagementService
{
    /**
     * Story 06: Assign students to an exam session
     */
    public function assignStudents(ExamSession $session, array $studentIds): Collection
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $sheets = collect();
            $roomAssignments = $session->roomAssignments()
                ->orderBy('id')
                ->get();

            if ($roomAssignments->isEmpty()) {
                throw new \Exception('No rooms assigned to this exam session');
            }

            $currentRoomIndex = 0;
            $currentSeatNumber = $roomAssignments[$currentRoomIndex]->seat_start_number ?? 1;

            foreach ($studentIds as $studentId) {
                $currentRoom = $roomAssignments[$currentRoomIndex];

                // Check if current room is full
                if ($currentRoom->assigned_students >= $currentRoom->capacity) {
                    $currentRoomIndex++;
                    if ($currentRoomIndex >= $roomAssignments->count()) {
                        throw new \Exception('Not enough room capacity for all students');
                    }
                    $currentRoom = $roomAssignments[$currentRoomIndex];
                    $currentSeatNumber = $currentRoom->seat_start_number ?? 1;
                }

                // Create attendance sheet
                $sheet = ExamAttendanceSheet::create([
                    'exam_session_id' => $session->id,
                    'exam_room_assignment_id' => $currentRoom->id,
                    'student_id' => $studentId,
                    'seat_number' => $currentSeatNumber,
                    'status' => 'present',
                ]);

                // Update room assignment count
                $currentRoom->increment('assigned_students');
                $currentSeatNumber++;

                $sheets->push($sheet);
            }

            DB::connection('tenant')->commit();

            return $sheets;
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            throw $e;
        }
    }

    /**
     * Story 06: Auto-assign students from module enrollment
     */
    public function autoAssignStudentsFromModule(ExamSession $session): Collection
    {
        $students = Student::query()
            ->whereHas('enrolledModules', function ($q) use ($session) {
                $q->where('module_id', $session->module_id)
                    ->where('academic_year_id', $session->academic_year_id);
            })
            ->pluck('id')
            ->toArray();

        return $this->assignStudents($session, $students);
    }

    /**
     * Story 06: Reassign student to different room
     */
    public function reassignStudent(ExamAttendanceSheet $sheet, int $newRoomAssignmentId, ?string $newSeatNumber = null): ExamAttendanceSheet
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $oldRoom = $sheet->roomAssignment;
            $newRoom = ExamRoomAssignment::findOrFail($newRoomAssignmentId);

            // Check capacity
            if ($newRoom->assigned_students >= $newRoom->capacity) {
                throw new \Exception('Target room is full');
            }

            // Update sheet
            $sheet->update([
                'exam_room_assignment_id' => $newRoomAssignmentId,
                'seat_number' => $newSeatNumber ?? ($newRoom->seat_start_number ?? 1) + $newRoom->assigned_students,
            ]);

            // Update room counts
            $oldRoom->decrement('assigned_students');
            $newRoom->increment('assigned_students');

            DB::connection('tenant')->commit();

            return $sheet->fresh();
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            throw $e;
        }
    }

    /**
     * Story 07: Generate exam preparation checklist
     */
    public function generatePreparationChecklist(ExamSession $session): array
    {
        return [
            'session_details' => [
                'title' => $session->title,
                'date' => $session->exam_date,
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'duration' => $session->duration_minutes.' minutes',
            ],
            'rooms' => $session->roomAssignments()->with('room')->get()->map(fn ($r) => [
                'room' => $r->room->name,
                'capacity' => $r->capacity,
                'assigned' => $r->assigned_students,
                'seats' => ($r->seat_start_number ?? 1).' - '.($r->seat_end_number ?? $r->capacity),
            ]),
            'supervisors_needed' => $this->calculateSupervisorsNeeded($session),
            'students_count' => $session->attendanceSheets()->count(),
            'materials_needed' => $session->allowed_materials ?? [],
            'instructions' => $session->instructions,
            'checklist_items' => [
                'Exam papers printed' => false,
                'Answer sheets prepared' => false,
                'Attendance sheets printed' => false,
                'Room setup completed' => false,
                'Supervisors confirmed' => $this->areSupervisorsConfirmed($session),
                'Students notified' => false,
                'Materials distributed' => false,
            ],
        ];
    }

    /**
     * Story 07: Calculate required supervisors
     */
    private function calculateSupervisorsNeeded(ExamSession $session): int
    {
        $totalStudents = $session->attendanceSheets()->count();

        // 1 supervisor per 30 students minimum
        return max(1, (int) ceil($totalStudents / 30));
    }

    /**
     * Story 07: Check if supervisors are confirmed
     */
    private function areSupervisorsConfirmed(ExamSession $session): bool
    {
        $totalSupervisors = $session->supervisors()->count();
        $confirmedSupervisors = $session->supervisors()->where('status', 'confirmed')->count();

        return $totalSupervisors > 0 && $totalSupervisors === $confirmedSupervisors;
    }

    /**
     * Story 05: Update session materials
     */
    public function updateAllowedMaterials(ExamSession $session, array $materials): ExamSession
    {
        $session->update([
            'allowed_materials' => $materials,
        ]);

        return $session;
    }

    /**
     * Story 05: Update exam instructions
     */
    public function updateInstructions(ExamSession $session, string $instructions): ExamSession
    {
        $session->update([
            'instructions' => $instructions,
        ]);

        return $session;
    }

    /**
     * Story 06: Remove student from exam
     */
    public function removeStudent(ExamAttendanceSheet $sheet): bool
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $roomAssignment = $sheet->roomAssignment;
            $sheet->delete();

            // Decrement room count
            if ($roomAssignment) {
                $roomAssignment->decrement('assigned_students');
            }

            DB::connection('tenant')->commit();

            return true;
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            throw $e;
        }
    }

    /**
     * Get students eligible for an exam (enrolled in the module)
     */
    public function getEligibleStudents(ExamSession $session): Collection
    {
        return Student::query()
            ->whereHas('enrolledModules', function ($q) use ($session) {
                $q->where('module_id', $session->module_id)
                    ->where('academic_year_id', $session->academic_year_id);
            })
            ->whereDoesntHave('examAttendanceSheets', function ($q) use ($session) {
                $q->where('exam_session_id', $session->id);
            })
            ->get();
    }
}
