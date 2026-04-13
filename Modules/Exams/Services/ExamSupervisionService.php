<?php

namespace Modules\Exams\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Exams\Entities\ExamAttendanceSheet;
use Modules\Exams\Entities\ExamIncident;
use Modules\Exams\Entities\ExamSession;
use Modules\Exams\Entities\ExamSupervisor;

/**
 * Service for Exam Supervision (Epic 3: Stories 08-10)
 * - Story 08: Assign supervisors to exam sessions
 * - Story 09: Track student attendance during exam
 * - Story 10: Report and manage exam incidents
 */
class ExamSupervisionService
{
    /**
     * Story 08: Assign a supervisor to an exam session
     */
    public function assignSupervisor(
        ExamSession $session,
        int $teacherId,
        ?int $roomAssignmentId = null,
        string $role = 'principal'
    ): ExamSupervisor {
        return ExamSupervisor::create([
            'exam_session_id' => $session->id,
            'exam_room_assignment_id' => $roomAssignmentId,
            'teacher_id' => $teacherId,
            'role' => $role,
            'status' => 'assigned',
        ]);
    }

    /**
     * Story 08: Assign multiple supervisors
     */
    public function assignMultipleSupervisors(ExamSession $session, array $supervisors): Collection
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $assigned = collect();

            foreach ($supervisors as $supervisorData) {
                $supervisor = $this->assignSupervisor(
                    $session,
                    $supervisorData['teacher_id'],
                    $supervisorData['room_assignment_id'] ?? null,
                    $supervisorData['role'] ?? 'principal'
                );

                $assigned->push($supervisor);
            }

            DB::connection('tenant')->commit();

            return $assigned;
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            throw $e;
        }
    }

    /**
     * Story 08: Mark supervisor as present
     */
    public function markSupervisorPresent(ExamSupervisor $supervisor): ExamSupervisor
    {
        $supervisor->update([
            'status' => 'present',
            'actual_start_time' => now()->format('H:i'),
        ]);

        return $supervisor;
    }

    /**
     * Story 08: Replace absent supervisor
     */
    public function replaceSupervisor(ExamSupervisor $absentSupervisor, int $replacementTeacherId): ExamSupervisor
    {
        DB::connection('tenant')->beginTransaction();

        try {
            // Mark original as replaced
            $absentSupervisor->update(['status' => 'replaced']);

            // Create new supervisor assignment
            $replacement = $this->assignSupervisor(
                $absentSupervisor->examSession,
                $replacementTeacherId,
                $absentSupervisor->exam_room_assignment_id,
                $absentSupervisor->role
            );

            DB::connection('tenant')->commit();

            return $replacement;
        } catch (\Exception $e) {
            DB::connection('tenant')->rollBack();
            throw $e;
        }
    }

    /**
     * Story 09: Record student attendance during exam
     */
    public function recordStudentAttendance(
        ExamAttendanceSheet $sheet,
        string $status,
        ?array $data = []
    ): ExamAttendanceSheet {
        $updateData = array_merge($data, [
            'status' => $status,
        ]);

        // Set arrival time if marked present/late
        if (in_array($status, ['present', 'late']) && ! $sheet->arrival_time) {
            $updateData['arrival_time'] = now()->format('H:i');
        }

        $sheet->update($updateData);

        return $sheet->fresh();
    }

    /**
     * Story 09: Mark student submission
     */
    public function recordSubmission(ExamAttendanceSheet $sheet): ExamAttendanceSheet
    {
        $sheet->update([
            'has_submitted' => true,
            'submission_time' => now()->format('H:i'),
        ]);

        return $sheet;
    }

    /**
     * Story 09: Verify attendance sheet
     */
    public function verifyAttendanceSheet(ExamAttendanceSheet $sheet, int $verifierId): ExamAttendanceSheet
    {
        $sheet->update([
            'verified_by' => $verifierId,
            'verified_at' => now(),
        ]);

        return $sheet;
    }

    /**
     * Story 10: Report an incident during exam
     */
    public function reportIncident(array $data): ExamIncident
    {
        return ExamIncident::create(array_merge($data, [
            'status' => 'reported',
            'reported_by' => auth()->id(),
        ]));
    }

    /**
     * Story 10: Update incident status
     */
    public function updateIncidentStatus(ExamIncident $incident, string $status, ?array $data = []): ExamIncident
    {
        $updateData = array_merge($data, ['status' => $status]);

        if ($status === 'resolved') {
            $updateData['reviewed_by'] = auth()->id();
            $updateData['reviewed_at'] = now();
        }

        $incident->update($updateData);

        return $incident->fresh();
    }

    /**
     * Story 10: Add incident evidence
     */
    public function addIncidentEvidence(ExamIncident $incident, string $evidencePath): ExamIncident
    {
        $incident->update(['evidence_path' => $evidencePath]);

        return $incident;
    }

    /**
     * Story 10: Escalate incident
     */
    public function escalateIncident(ExamIncident $incident, string $reason): ExamIncident
    {
        $incident->update([
            'status' => 'escalated',
            'severity' => 'critical',
            'action_taken' => $reason,
        ]);

        return $incident;
    }

    /**
     * Get supervisor schedule for a teacher
     */
    public function getSupervisorSchedule(int $teacherId, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = ExamSupervisor::query()
            ->where('teacher_id', $teacherId)
            ->with(['examSession', 'roomAssignment']);

        if ($startDate && $endDate) {
            $query->whereHas('examSession', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('exam_date', [$startDate, $endDate]);
            });
        }

        return $query->get();
    }

    /**
     * Get real-time attendance statistics for an exam
     */
    public function getAttendanceStatistics(ExamSession $session): array
    {
        $total = $session->attendanceSheets()->count();
        $present = $session->attendanceSheets()->present()->count();
        $absent = $session->attendanceSheets()->absent()->count();
        $late = $session->attendanceSheets()->where('status', 'late')->count();
        $submitted = $session->attendanceSheets()->where('has_submitted', true)->count();

        return [
            'total_students' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'submitted' => $submitted,
            'present_percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
            'submission_percentage' => $total > 0 ? round(($submitted / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get incidents summary for an exam
     */
    public function getIncidentsSummary(ExamSession $session): array
    {
        $incidents = $session->incidents;

        return [
            'total' => $incidents->count(),
            'by_type' => $incidents->groupBy('type')->map->count(),
            'by_severity' => $incidents->groupBy('severity')->map->count(),
            'by_status' => $incidents->groupBy('status')->map->count(),
            'pending' => $incidents->where('status', 'reported')->count(),
            'resolved' => $incidents->where('status', 'resolved')->count(),
        ];
    }
}
