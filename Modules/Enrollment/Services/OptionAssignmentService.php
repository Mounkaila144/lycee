<?php

namespace Modules\Enrollment\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Enrollment\Entities\Option;
use Modules\Enrollment\Entities\OptionAssignment;
use Modules\Enrollment\Entities\OptionChoice;
use Modules\Enrollment\Entities\Student;
use Modules\StructureAcademique\Entities\AcademicYear;
use Modules\StructureAcademique\Entities\Programme;
use Modules\UsersGuard\Entities\User;

class OptionAssignmentService
{
    /**
     * Result structure for assignment operations
     *
     * @var array{
     *     assigned: int,
     *     waitlist: int,
     *     unassigned: int,
     *     assignments: Collection,
     *     waitlist_students: array,
     *     unassigned_students: array,
     *     errors: array
     * }
     */
    private array $result = [
        'assigned' => 0,
        'waitlist' => 0,
        'unassigned' => 0,
        'assignments' => [],
        'waitlist_students' => [],
        'unassigned_students' => [],
        'errors' => [],
    ];

    /**
     * Assign options automatically for a specific programme and level
     */
    public function assignOptionsAutomatically(
        AcademicYear $academicYear,
        Programme $programme,
        string $level
    ): array {
        $this->resetResult();

        // Get all open options for this programme and level
        $options = Option::query()
            ->where('programme_id', $programme->id)
            ->where('level', $level)
            ->where('status', 'Open')
            ->get()
            ->keyBy('id');

        if ($options->isEmpty()) {
            $this->result['errors'][] = 'Aucune option ouverte trouvée pour ce programme et niveau.';

            return $this->result;
        }

        // Get all pending choices grouped by student, ordered by average grade (highest first)
        $choicesByStudent = $this->getOrderedChoicesByStudent($academicYear, $programme, $level);

        if ($choicesByStudent->isEmpty()) {
            $this->result['errors'][] = 'Aucun vœu en attente trouvé.';

            return $this->result;
        }

        // Track remaining capacity for each option
        $remainingCapacity = $options->mapWithKeys(fn ($opt) => [
            $opt->id => $opt->getRemainingCapacity($academicYear->id),
        ])->toArray();

        DB::beginTransaction();

        try {
            foreach ($choicesByStudent as $studentId => $studentChoices) {
                $this->processStudentChoices(
                    $studentId,
                    $studentChoices,
                    $options,
                    $remainingCapacity,
                    $academicYear
                );
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Option assignment failed', [
                'error' => $e->getMessage(),
                'academic_year_id' => $academicYear->id,
                'programme_id' => $programme->id,
                'level' => $level,
            ]);
            $this->result['errors'][] = 'Erreur lors de l\'affectation: '.$e->getMessage();
        }

        $this->result['assignments'] = collect($this->result['assignments']);

        return $this->result;
    }

    /**
     * Manually assign a student to an option
     */
    public function assignManually(
        Student $student,
        Option $option,
        AcademicYear $academicYear,
        User $assignedBy,
        ?string $notes = null,
        ?int $choiceRankObtained = null
    ): OptionAssignment {
        // Check if student already has an assignment for this year
        $existingAssignment = OptionAssignment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', $academicYear->id)
            ->first();

        if ($existingAssignment) {
            // Update existing assignment
            $existingAssignment->update([
                'option_id' => $option->id,
                'choice_rank_obtained' => $choiceRankObtained ?? $this->findChoiceRank($student, $option, $academicYear),
                'assignment_method' => 'Manual',
                'assigned_by' => $assignedBy->id,
                'assignment_notes' => $notes,
                'assigned_at' => now(),
            ]);

            return $existingAssignment->fresh();
        }

        // Create new assignment
        return OptionAssignment::create([
            'student_id' => $student->id,
            'option_id' => $option->id,
            'academic_year_id' => $academicYear->id,
            'choice_rank_obtained' => $choiceRankObtained ?? $this->findChoiceRank($student, $option, $academicYear),
            'assignment_method' => 'Manual',
            'assigned_by' => $assignedBy->id,
            'assignment_notes' => $notes,
            'assigned_at' => now(),
        ]);
    }

    /**
     * Remove an assignment
     */
    public function removeAssignment(OptionAssignment $assignment): bool
    {
        return $assignment->delete();
    }

    /**
     * Check if a student meets prerequisites for an option
     */
    public function checkPrerequisites(Student $student, Option $option): array
    {
        $result = [
            'passes' => true,
            'missing' => [],
            'details' => [],
        ];

        if (! $option->hasPrerequisites()) {
            return $result;
        }

        $prerequisites = $option->getPrerequisitesList();

        foreach ($prerequisites as $moduleId => $minGrade) {
            $studentGrade = $this->getStudentGradeForModule($student, $moduleId);

            if ($studentGrade === null || $studentGrade < $minGrade) {
                $result['passes'] = false;
                $result['missing'][] = [
                    'module_id' => $moduleId,
                    'required_grade' => $minGrade,
                    'student_grade' => $studentGrade,
                ];
            }

            $result['details'][] = [
                'module_id' => $moduleId,
                'required_grade' => $minGrade,
                'student_grade' => $studentGrade,
                'passes' => $studentGrade !== null && $studentGrade >= $minGrade,
            ];
        }

        return $result;
    }

    /**
     * Get assignment statistics for an option
     */
    public function getOptionStatistics(Option $option, AcademicYear $academicYear): array
    {
        $assignments = OptionAssignment::query()
            ->where('option_id', $option->id)
            ->where('academic_year_id', $academicYear->id)
            ->get();

        $choices = OptionChoice::query()
            ->where('option_id', $option->id)
            ->where('academic_year_id', $academicYear->id)
            ->get();

        $totalAssigned = $assignments->count();
        $firstChoiceCount = $assignments->where('choice_rank_obtained', 1)->count();
        $secondChoiceCount = $assignments->where('choice_rank_obtained', 2)->count();
        $thirdChoiceCount = $assignments->where('choice_rank_obtained', 3)->count();
        $automaticCount = $assignments->where('assignment_method', 'Automatic')->count();
        $manualCount = $assignments->where('assignment_method', 'Manual')->count();

        return [
            'option_id' => $option->id,
            'option_name' => $option->name,
            'capacity' => $option->capacity,
            'total_assigned' => $totalAssigned,
            'remaining_capacity' => $option->capacity - $totalAssigned,
            'fill_rate' => $option->capacity > 0 ? round(($totalAssigned / $option->capacity) * 100, 2) : 0,
            'total_choices' => $choices->count(),
            'first_choice_demands' => $choices->where('choice_rank', 1)->count(),
            'assignment_breakdown' => [
                'first_choice_obtained' => $firstChoiceCount,
                'second_choice_obtained' => $secondChoiceCount,
                'third_choice_obtained' => $thirdChoiceCount,
            ],
            'satisfaction_rate' => $totalAssigned > 0
                ? round(($firstChoiceCount / $totalAssigned) * 100, 2)
                : 0,
            'method_breakdown' => [
                'automatic' => $automaticCount,
                'manual' => $manualCount,
            ],
        ];
    }

    /**
     * Get global statistics for all options in a programme/level
     */
    public function getGlobalStatistics(
        AcademicYear $academicYear,
        Programme $programme,
        string $level
    ): array {
        $options = Option::query()
            ->where('programme_id', $programme->id)
            ->where('level', $level)
            ->get();

        $totalStudentsWithChoices = OptionChoice::query()
            ->whereIn('option_id', $options->pluck('id'))
            ->where('academic_year_id', $academicYear->id)
            ->distinct('student_id')
            ->count('student_id');

        $totalAssignments = OptionAssignment::query()
            ->whereIn('option_id', $options->pluck('id'))
            ->where('academic_year_id', $academicYear->id)
            ->get();

        $firstChoiceObtained = $totalAssignments->where('choice_rank_obtained', 1)->count();

        $optionStats = $options->map(fn ($option) => $this->getOptionStatistics($option, $academicYear));

        return [
            'programme_id' => $programme->id,
            'programme_name' => $programme->libelle,
            'level' => $level,
            'academic_year' => $academicYear->name,
            'total_options' => $options->count(),
            'total_capacity' => $options->sum('capacity'),
            'total_students_with_choices' => $totalStudentsWithChoices,
            'total_assigned' => $totalAssignments->count(),
            'total_unassigned' => $totalStudentsWithChoices - $totalAssignments->count(),
            'first_choice_satisfaction_rate' => $totalAssignments->count() > 0
                ? round(($firstChoiceObtained / $totalAssignments->count()) * 100, 2)
                : 0,
            'options' => $optionStats,
        ];
    }

    /**
     * Reset the result array
     */
    private function resetResult(): void
    {
        $this->result = [
            'assigned' => 0,
            'waitlist' => 0,
            'unassigned' => 0,
            'assignments' => [],
            'waitlist_students' => [],
            'unassigned_students' => [],
            'errors' => [],
        ];
    }

    /**
     * Get choices grouped by student, ordered by average grade
     */
    private function getOrderedChoicesByStudent(
        AcademicYear $academicYear,
        Programme $programme,
        string $level
    ): Collection {
        // Get all options for this programme/level
        $optionIds = Option::query()
            ->where('programme_id', $programme->id)
            ->where('level', $level)
            ->pluck('id');

        // Get all pending choices for these options
        $choices = OptionChoice::query()
            ->whereIn('option_id', $optionIds)
            ->where('academic_year_id', $academicYear->id)
            ->where('status', 'Pending')
            ->with('student')
            ->get();

        // Group by student and calculate average grades
        $grouped = $choices->groupBy('student_id');

        // Sort by average grade (highest first)
        return $grouped->sortByDesc(function ($studentChoices) {
            $student = $studentChoices->first()->student;

            return $this->calculateStudentAverageGrade($student);
        });
    }

    /**
     * Process choices for a single student
     */
    private function processStudentChoices(
        int $studentId,
        Collection $studentChoices,
        Collection $options,
        array &$remainingCapacity,
        AcademicYear $academicYear
    ): void {
        $student = $studentChoices->first()->student;

        // Sort by choice rank (1 first, then 2, then 3)
        $sortedChoices = $studentChoices->sortBy('choice_rank');

        $assigned = false;

        foreach ($sortedChoices as $choice) {
            $option = $options->get($choice->option_id);

            if (! $option) {
                continue;
            }

            // Check prerequisites
            $prerequisiteCheck = $this->checkPrerequisites($student, $option);
            if (! $prerequisiteCheck['passes']) {
                $choice->update(['status' => 'Rejected']);

                continue;
            }

            // Check capacity
            if (($remainingCapacity[$option->id] ?? 0) > 0) {
                // Create assignment
                $assignment = OptionAssignment::create([
                    'student_id' => $studentId,
                    'option_id' => $option->id,
                    'academic_year_id' => $academicYear->id,
                    'choice_rank_obtained' => $choice->choice_rank,
                    'assignment_method' => 'Automatic',
                    'assigned_at' => now(),
                ]);

                $this->result['assignments'][] = $assignment;
                $this->result['assigned']++;

                // Update choice status
                $choice->update(['status' => 'Validated']);

                // Update remaining capacity
                $remainingCapacity[$option->id]--;

                // Reject other choices for this student
                OptionChoice::query()
                    ->where('student_id', $studentId)
                    ->where('academic_year_id', $academicYear->id)
                    ->where('id', '!=', $choice->id)
                    ->where('status', 'Pending')
                    ->update(['status' => 'Rejected']);

                $assigned = true;

                break;
            } else {
                // Add to waitlist with score
                $this->result['waitlist_students'][] = [
                    'student_id' => $studentId,
                    'option_id' => $option->id,
                    'choice_rank' => $choice->choice_rank,
                    'average_grade' => $this->calculateStudentAverageGrade($student),
                ];
                $this->result['waitlist']++;
            }
        }

        if (! $assigned) {
            $this->result['unassigned_students'][] = [
                'student_id' => $studentId,
                'student_name' => $student->full_name ?? "{$student->firstname} {$student->lastname}",
            ];
            $this->result['unassigned']++;

            Log::warning('Student not assigned to any option', [
                'student_id' => $studentId,
                'academic_year_id' => $academicYear->id,
            ]);
        }
    }

    /**
     * Calculate average grade for a student
     * This is a placeholder that should be enhanced when Grades module is available
     */
    private function calculateStudentAverageGrade(Student $student): float
    {
        // TODO: Implement when Grades module is available
        // For now, return a random value for sorting (will be replaced)
        return 10.0; // Default neutral grade
    }

    /**
     * Get student's grade for a specific module
     * This is a placeholder that should be enhanced when Grades module is available
     */
    private function getStudentGradeForModule(Student $student, int $moduleId): ?float
    {
        // TODO: Implement when Grades module is available
        // Return null to indicate grade not available
        return null;
    }

    /**
     * Find the choice rank for a student-option combination
     */
    private function findChoiceRank(Student $student, Option $option, AcademicYear $academicYear): int
    {
        $choice = OptionChoice::query()
            ->where('student_id', $student->id)
            ->where('option_id', $option->id)
            ->where('academic_year_id', $academicYear->id)
            ->first();

        return $choice?->choice_rank ?? 0;
    }
}
