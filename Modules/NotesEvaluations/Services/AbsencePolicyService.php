<?php

namespace Modules\NotesEvaluations\Services;

use Modules\Enrollment\Entities\Student;
use Modules\NotesEvaluations\Entities\AbsenceJustification;
use Modules\NotesEvaluations\Entities\Grade;
use Modules\NotesEvaluations\Entities\GradeAbsence;
use Modules\NotesEvaluations\Entities\ModuleAbsenceSettings;
use Modules\NotesEvaluations\Entities\ReplacementEvaluation;
use Modules\NotesEvaluations\Notifications\AbsenceJustificationReviewedNotification;
use Modules\NotesEvaluations\Notifications\ReplacementEvaluationScheduledNotification;
use Modules\StructureAcademique\Entities\ModuleEvaluationConfig;
use Modules\UsersGuard\Entities\User;

class AbsencePolicyService
{
    /**
     * Get absence policy for an evaluation
     */
    public function getPolicy(ModuleEvaluationConfig $evaluation): object
    {
        $settings = ModuleAbsenceSettings::getForModule($evaluation->module_id);

        return (object) [
            'unjustified_grade_is_zero' => $settings->unjustified_grade_is_zero,
            'allow_replacement_evaluation' => $settings->allow_replacement_evaluation,
            'justification_deadline_days' => $settings->justification_deadline_days,
            'auto_reminder_enabled' => $settings->auto_reminder_enabled,
        ];
    }

    /**
     * Mark a grade as absent
     */
    public function markAbsent(
        Grade $grade,
        string $absenceType = 'unjustified'
    ): GradeAbsence {
        $grade->update([
            'is_absent' => true,
            'score' => null,
        ]);

        $policy = $this->getPolicy($grade->evaluation);
        $deadline = now()->addDays($policy->justification_deadline_days);

        return GradeAbsence::updateOrCreate(
            ['grade_id' => $grade->id],
            [
                'absence_type' => $absenceType,
                'justification_deadline' => $deadline,
            ]
        );
    }

    /**
     * Bulk mark students as absent
     *
     * @return array{marked: int, errors: array}
     */
    public function bulkMarkAbsent(
        ModuleEvaluationConfig $evaluation,
        array $studentIds,
        User $teacher,
        string $absenceType = 'unjustified'
    ): array {
        $results = [
            'marked' => 0,
            'errors' => [],
        ];

        foreach ($studentIds as $studentId) {
            try {
                $grade = Grade::firstOrCreate(
                    [
                        'student_id' => $studentId,
                        'evaluation_id' => $evaluation->id,
                    ],
                    [
                        'score' => null,
                        'is_absent' => true,
                        'entered_by' => $teacher->id,
                        'entered_at' => now(),
                        'status' => 'Draft',
                    ]
                );

                $this->markAbsent($grade, $absenceType);
                $results['marked']++;
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'student_id' => $studentId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Upload justification document
     */
    public function uploadJustification(
        Student $student,
        ModuleEvaluationConfig $evaluation,
        $file
    ): AbsenceJustification {
        // Store file
        $path = $file->store(
            "justifications/{$evaluation->module_id}/{$student->id}",
            'tenant'
        );

        $justification = AbsenceJustification::create([
            'student_id' => $student->id,
            'evaluation_id' => $evaluation->id,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        // Update absence to pending
        $grade = Grade::where('student_id', $student->id)
            ->where('evaluation_id', $evaluation->id)
            ->first();

        if ($grade && $grade->absence) {
            $grade->absence->update([
                'absence_type' => 'pending',
                'justification_id' => $justification->id,
            ]);
        }

        return $justification;
    }

    /**
     * Approve a justification
     */
    public function approveJustification(
        AbsenceJustification $justification,
        User $reviewer,
        ?string $comment = null
    ): void {
        $justification->approve($reviewer, $comment);

        // Update absence to justified
        $grade = Grade::where('student_id', $justification->student_id)
            ->where('evaluation_id', $justification->evaluation_id)
            ->first();

        if ($grade && $grade->absence) {
            $grade->absence->markAsJustified();
        }

        // Notify student
        $justification->student->notify(
            new AbsenceJustificationReviewedNotification($justification, 'approved')
        );
    }

    /**
     * Reject a justification
     */
    public function rejectJustification(
        AbsenceJustification $justification,
        User $reviewer,
        string $comment
    ): void {
        $justification->reject($reviewer, $comment);

        // Revert absence to unjustified
        $grade = Grade::where('student_id', $justification->student_id)
            ->where('evaluation_id', $justification->evaluation_id)
            ->first();

        if ($grade && $grade->absence) {
            $grade->absence->update(['absence_type' => 'unjustified']);
        }

        // Notify student
        $justification->student->notify(
            new AbsenceJustificationReviewedNotification($justification, 'rejected')
        );
    }

    /**
     * Schedule a replacement evaluation
     */
    public function scheduleReplacementEvaluation(
        ModuleEvaluationConfig $originalEvaluation,
        Student $student,
        \DateTimeInterface $scheduledAt,
        ?string $location = null,
        string $type = 'same',
        ?string $comment = null
    ): ReplacementEvaluation {
        $policy = $this->getPolicy($originalEvaluation);

        if (! $policy->allow_replacement_evaluation) {
            throw new \Exception('Les évaluations de remplacement ne sont pas autorisées pour ce module.');
        }

        $replacement = ReplacementEvaluation::create([
            'original_evaluation_id' => $originalEvaluation->id,
            'student_id' => $student->id,
            'scheduled_at' => $scheduledAt,
            'location' => $location,
            'type' => $type,
            'comment' => $comment,
            'status' => 'scheduled',
        ]);

        // Notify student
        $student->notify(new ReplacementEvaluationScheduledNotification($replacement));

        return $replacement;
    }

    /**
     * Apply absence policy to grade for average calculation
     */
    public function applyPolicyToGrade(Grade $grade): void
    {
        if (! $grade->is_absent) {
            return;
        }

        $absence = $grade->absence;
        $policy = $this->getPolicy($grade->evaluation);

        if ($absence && $absence->absence_type === 'unjustified' && $policy->unjustified_grade_is_zero) {
            $grade->score = 0;
        } else {
            $grade->score = null; // Exclude from calculation
        }

        $grade->save();
    }

    /**
     * Get absence statistics for an evaluation
     */
    public function getAbsenceStatistics(ModuleEvaluationConfig $evaluation): array
    {
        $absences = GradeAbsence::whereHas('grade', function ($q) use ($evaluation) {
            $q->where('evaluation_id', $evaluation->id);
        })->get();

        return [
            'total' => $absences->count(),
            'unjustified' => $absences->where('absence_type', 'unjustified')->count(),
            'pending' => $absences->where('absence_type', 'pending')->count(),
            'justified' => $absences->where('absence_type', 'justified')->count(),
            'rate' => $this->calculateAbsenceRate($evaluation),
        ];
    }

    /**
     * Calculate absence rate for an evaluation
     */
    private function calculateAbsenceRate(ModuleEvaluationConfig $evaluation): float
    {
        $totalGrades = Grade::where('evaluation_id', $evaluation->id)->count();
        $absences = Grade::where('evaluation_id', $evaluation->id)
            ->where('is_absent', true)
            ->count();

        return $totalGrades > 0 ? round(($absences / $totalGrades) * 100, 2) : 0;
    }
}
