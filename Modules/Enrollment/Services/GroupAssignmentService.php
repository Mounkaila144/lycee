<?php

namespace Modules\Enrollment\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Entities\Group;
use Modules\Enrollment\Entities\GroupAssignment;
use Modules\Enrollment\Entities\Student;

class GroupAssignmentService
{
    /**
     * Preview auto-assignment without actually creating assignments
     * Returns what the assignment would look like
     */
    public function previewAutoAssign(array $studentIds, array $groupIds, string $method = 'balanced'): array
    {
        $groups = Group::on('tenant')->whereIn('id', $groupIds)->where('status', 'Active')->get();
        if ($groups->isEmpty()) {
            return ['preview' => [], 'errors' => ['No active group found'], 'stats' => []];
        }

        $students = Student::on('tenant')->whereIn('id', $studentIds)->where('status', 'Actif')->get();
        if ($students->isEmpty()) {
            return ['preview' => [], 'errors' => ['No active student found'], 'stats' => []];
        }

        $orderedStudents = match ($method) {
            'alphabetic' => $students->sortBy(fn ($s) => $s->lastname),
            'random' => $students->shuffle(),
            'option' => $students->sortBy(fn ($s) => $s->option_id ?? 0),
            default => $students,
        };

        $preview = [];
        $errors = [];
        $assignedCount = 0;
        $ref = $groups->first();
        $moduleId = $ref->module_id;
        $academicYearId = $ref->academic_year_id;

        // Track simulated assignments per group
        $simulatedCounts = $groups->mapWithKeys(fn ($g) => [$g->id => $g->current_count]);

        foreach ($orderedStudents as $student) {
            $existing = GroupAssignment::on('tenant')
                ->where('student_id', $student->id)
                ->where('module_id', $moduleId)
                ->where('academic_year_id', $academicYearId)
                ->first();

            if ($existing) {
                $errors[] = "Étudiant {$student->matricule} ({$student->lastname} {$student->firstname}) déjà affecté";

                continue;
            }

            // Find best group considering simulated counts
            $targetGroup = $groups
                ->filter(function ($g) use ($simulatedCounts) {
                    return $g->isActive() && ($simulatedCounts[$g->id] ?? 0) < $g->capacity_max;
                })
                ->sortBy(fn ($g) => ($simulatedCounts[$g->id] ?? 0) / $g->capacity_max)
                ->first();

            if (! $targetGroup) {
                $errors[] = "Aucun groupe disponible pour {$student->matricule} ({$student->lastname} {$student->firstname})";

                continue;
            }

            $preview[] = [
                'student' => [
                    'id' => $student->id,
                    'matricule' => $student->matricule,
                    'lastname' => $student->lastname,
                    'firstname' => $student->firstname,
                ],
                'group' => [
                    'id' => $targetGroup->id,
                    'code' => $targetGroup->code,
                    'name' => $targetGroup->name,
                    'type' => $targetGroup->type,
                ],
            ];

            $simulatedCounts[$targetGroup->id] = ($simulatedCounts[$targetGroup->id] ?? 0) + 1;
            $assignedCount++;
        }

        // Calculate projected group stats
        $groupStats = $groups->map(function ($g) use ($simulatedCounts) {
            $projectedCount = $simulatedCounts[$g->id] ?? $g->current_count;

            return [
                'group_id' => $g->id,
                'code' => $g->code,
                'name' => $g->name,
                'current_count' => $g->current_count,
                'projected_count' => $projectedCount,
                'capacity_max' => $g->capacity_max,
                'projected_fill_rate' => $g->capacity_max > 0 ? round(($projectedCount / $g->capacity_max) * 100, 2) : 0,
            ];
        })->values()->toArray();

        return [
            'preview' => $preview,
            'errors' => $errors,
            'stats' => [
                'total_students' => $students->count(),
                'will_be_assigned' => $assignedCount,
                'will_fail' => count($errors),
                'method' => $method,
            ],
            'group_stats' => $groupStats,
        ];
    }

    public function autoAssign(array $studentIds, array $groupIds, string $method = 'balanced'): array
    {
        return DB::connection('tenant')->transaction(function () use ($studentIds, $groupIds, $method) {
            $groups = Group::on('tenant')->whereIn('id', $groupIds)->where('status', 'Active')->get();
            if ($groups->isEmpty()) {
                return ['assignments' => collect(), 'errors' => ['No active group found'], 'stats' => []];
            }
            $students = Student::on('tenant')->whereIn('id', $studentIds)->where('status', 'Actif')->get();
            if ($students->isEmpty()) {
                return ['assignments' => collect(), 'errors' => ['No active student found'], 'stats' => []];
            }

            $orderedStudents = match ($method) {
                'alphabetic' => $students->sortBy(fn ($s) => $s->lastname),
                'random' => $students->shuffle(),
                default => $students,
            };

            $assignments = collect();
            $errors = [];
            $assignedCount = 0;
            $ref = $groups->first();
            $moduleId = $ref->module_id;
            $academicYearId = $ref->academic_year_id;

            foreach ($orderedStudents as $student) {
                $existing = GroupAssignment::on('tenant')->where('student_id', $student->id)->where('module_id', $moduleId)->where('academic_year_id', $academicYearId)->first();
                if ($existing) {
                    $errors[] = "Student {$student->matricule} already assigned";

                    continue;
                }

                $targetGroup = $this->findBestGroup($groups);
                if (! $targetGroup) {
                    $errors[] = "No group available for {$student->matricule}";

                    continue;
                }

                try {
                    $assignment = GroupAssignment::on('tenant')->create([
                        'student_id' => $student->id, 'group_id' => $targetGroup->id, 'module_id' => $moduleId,
                        'academic_year_id' => $academicYearId, 'assignment_method' => 'Automatic',
                        'assigned_by' => Auth::id(), 'assignment_reason' => "Auto ({$method})", 'assigned_at' => now(),
                    ]);
                    $assignments->push($assignment);
                    $assignedCount++;
                    $groups = $groups->fresh();
                } catch (\Illuminate\Database\QueryException $e) {
                    // Handle duplicate entry gracefully (student might have been assigned by another process)
                    if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry')) {
                        $errors[] = "Student {$student->matricule} already assigned (duplicate detected)";

                        continue;
                    }
                    // Re-throw if it's a different error
                    throw $e;
                }
            }

            return ['assignments' => $assignments, 'errors' => $errors, 'stats' => ['total' => $students->count(), 'assigned' => $assignedCount, 'failed' => count($errors), 'method' => $method]];
        });
    }

    public function manualAssign(int $studentId, int $groupId, ?string $reason = null): array
    {
        return DB::connection('tenant')->transaction(function () use ($studentId, $groupId, $reason) {
            $group = Group::on('tenant')->find($groupId);
            if (! $group) {
                return ['assignment' => null, 'error' => 'Group not found'];
            }
            if (! $group->isActive()) {
                return ['assignment' => null, 'error' => 'Group not active'];
            }
            $student = Student::on('tenant')->find($studentId);
            if (! $student) {
                return ['assignment' => null, 'error' => 'Student not found'];
            }
            if ($group->hasStudent($studentId)) {
                return ['assignment' => null, 'error' => 'Student already in group'];
            }

            $existing = GroupAssignment::on('tenant')->where('student_id', $studentId)->where('module_id', $group->module_id)->where('academic_year_id', $group->academic_year_id)->first();
            if ($existing) {
                return ['assignment' => null, 'error' => 'Student already assigned to another group'];
            }
            if (! $group->canAcceptMoreStudents()) {
                return ['assignment' => null, 'error' => 'Group full'];
            }

            $assignment = GroupAssignment::on('tenant')->create([
                'student_id' => $studentId, 'group_id' => $groupId, 'module_id' => $group->module_id,
                'academic_year_id' => $group->academic_year_id, 'assignment_method' => 'Manual',
                'assigned_by' => Auth::id(), 'assignment_reason' => $reason ?? 'Manual assignment', 'assigned_at' => now(),
            ]);

            return ['assignment' => $assignment->load(['student', 'group']), 'error' => null];
        });
    }

    public function moveStudent(int $studentId, int $fromGroupId, int $toGroupId, ?string $reason = null): array
    {
        return DB::connection('tenant')->transaction(function () use ($studentId, $fromGroupId, $toGroupId, $reason) {
            $toGroup = Group::on('tenant')->find($toGroupId);
            if (! $toGroup) {
                return ['assignment' => null, 'error' => 'Destination group not found'];
            }
            if (! $toGroup->isActive()) {
                return ['assignment' => null, 'error' => 'Destination group not active'];
            }
            if (! $toGroup->canAcceptMoreStudents()) {
                return ['assignment' => null, 'error' => 'Destination group full'];
            }

            $existing = GroupAssignment::on('tenant')->where('student_id', $studentId)->where('group_id', $fromGroupId)->first();
            if (! $existing) {
                return ['assignment' => null, 'error' => 'Source assignment not found'];
            }

            $fromGroup = Group::on('tenant')->find($fromGroupId);
            if ($fromGroup->module_id !== $toGroup->module_id) {
                return ['assignment' => null, 'error' => 'Groups must be same module'];
            }

            $existing->delete();
            $newAssignment = GroupAssignment::on('tenant')->create([
                'student_id' => $studentId, 'group_id' => $toGroupId, 'module_id' => $toGroup->module_id,
                'academic_year_id' => $toGroup->academic_year_id, 'assignment_method' => 'Manual',
                'assigned_by' => Auth::id(), 'assignment_reason' => $reason ?? 'Group transfer', 'assigned_at' => now(),
            ]);

            return ['assignment' => $newAssignment->load(['student', 'group']), 'error' => null];
        });
    }

    public function removeStudent(int $assignmentId): array
    {
        $assignment = GroupAssignment::on('tenant')->find($assignmentId);
        if (! $assignment) {
            return ['success' => false, 'error' => 'Assignment not found'];
        }
        $assignment->delete();

        return ['success' => true, 'error' => null];
    }

    public function getGroupStats(int $groupId): array
    {
        $group = Group::on('tenant')->with(['module', 'programme', 'academicYear', 'semester', 'teacher'])->find($groupId);
        if (! $group) {
            return [];
        }

        return [
            'group_id' => $group->id, 'code' => $group->code, 'name' => $group->name, 'type' => $group->type, 'status' => $group->status,
            'capacity' => ['min' => $group->capacity_min, 'max' => $group->capacity_max, 'current' => $group->current_count, 'available' => $group->available_slots],
            'fill_rate' => $group->fill_rate, 'is_full' => $group->is_full, 'is_below_minimum' => $group->is_below_minimum,
            'can_accept_students' => $group->canAcceptMoreStudents(),
            'module' => $group->module ? ['id' => $group->module->id, 'code' => $group->module->code, 'name' => $group->module->name] : null,
            'teacher' => $group->teacher ? ['id' => $group->teacher->id, 'name' => $group->teacher->firstname.' '.$group->teacher->lastname] : null,
        ];
    }

    public function getMultipleGroupStats(array $groupIds): Collection
    {
        return collect($groupIds)->map(fn ($id) => $this->getGroupStats($id))->filter();
    }

    private function findBestGroup(Collection $groups): ?Group
    {
        return $groups->filter(fn ($g) => $g->canAcceptMoreStudents())->sortBy(fn ($g) => $g->fill_rate)->first();
    }
}
