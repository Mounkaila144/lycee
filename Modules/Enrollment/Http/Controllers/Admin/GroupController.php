<?php

namespace Modules\Enrollment\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Enrollment\Entities\Group;
use Modules\Enrollment\Entities\GroupAssignment;
use Modules\Enrollment\Exports\GroupStudentsExport;
use Modules\Enrollment\Http\Requests\AssignStudentToGroupRequest;
use Modules\Enrollment\Http\Requests\AutoAssignRequest;
use Modules\Enrollment\Http\Requests\StoreGroupRequest;
use Modules\Enrollment\Http\Requests\UpdateGroupRequest;
use Modules\Enrollment\Http\Resources\GroupAssignmentResource;
use Modules\Enrollment\Http\Resources\GroupResource;
use Modules\Enrollment\Services\GroupAssignmentService;

class GroupController extends Controller
{
    public function __construct(private GroupAssignmentService $assignmentService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Group::on('tenant')->with(['module', 'programme', 'academicYear', 'semester', 'teacher']);

        if ($moduleId = $request->input('module_id')) {
            $query->byModule($moduleId);
        }
        if ($programId = $request->input('program_id')) {
            $query->byProgramme($programId);
        }
        if ($level = $request->input('level')) {
            $query->byLevel($level);
        }
        if ($academicYearId = $request->input('academic_year_id')) {
            $query->byAcademicYear($academicYearId);
        }
        if ($semesterId = $request->input('semester_id')) {
            $query->bySemester($semesterId);
        }
        if ($type = $request->input('type')) {
            $query->byType($type);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 15);
        $groups = $query->paginate($perPage);

        return response()->json([
            'data' => GroupResource::collection($groups),
            'meta' => [
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'per_page' => $groups->perPage(),
                'total' => $groups->total(),
            ],
        ]);
    }

    public function store(StoreGroupRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['capacity_min'] = $validated['capacity_min'] ?? 20;
        $validated['capacity_max'] = $validated['capacity_max'] ?? 35;
        $validated['status'] = $validated['status'] ?? 'Active';

        $group = Group::on('tenant')->create($validated);
        $group->load(['module', 'programme', 'academicYear', 'semester', 'teacher']);

        return response()->json([
            'message' => 'Groupe créé avec succès',
            'data' => new GroupResource($group),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $group = Group::on('tenant')->with(['module', 'programme', 'academicYear', 'semester', 'teacher', 'assignments.student'])->findOrFail($id);

        return response()->json([
            'data' => new GroupResource($group),
        ]);
    }

    public function update(UpdateGroupRequest $request, int $id): JsonResponse
    {
        $group = Group::on('tenant')->findOrFail($id);
        $group->update($request->validated());
        $group->load(['module', 'programme', 'academicYear', 'semester', 'teacher']);

        return response()->json([
            'message' => 'Groupe mis à jour avec succès',
            'data' => new GroupResource($group),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $group = Group::on('tenant')->findOrFail($id);
        $group->delete();

        return response()->json([
            'message' => 'Groupe supprimé avec succès',
        ]);
    }

    public function previewAutoAssign(AutoAssignRequest $request): JsonResponse
    {
        $result = $this->assignmentService->previewAutoAssign(
            $request->input('student_ids'),
            $request->input('group_ids'),
            $request->getAssignmentMethod()
        );

        return response()->json([
            'message' => 'Prévisualisation de l\'affectation automatique',
            'data' => $result,
        ]);
    }

    public function autoAssign(AutoAssignRequest $request): JsonResponse
    {
        $result = $this->assignmentService->autoAssign(
            $request->input('student_ids'),
            $request->input('group_ids'),
            $request->getAssignmentMethod()
        );

        return response()->json([
            'message' => 'Affectation automatique terminée',
            'data' => [
                'assignments' => GroupAssignmentResource::collection($result['assignments']),
                'stats' => $result['stats'],
                'errors' => $result['errors'],
            ],
        ]);
    }

    public function assignStudent(AssignStudentToGroupRequest $request, int $id): JsonResponse
    {
        $result = $this->assignmentService->manualAssign(
            $request->input('student_id'),
            $id,
            $request->input('reason')
        );

        if ($result['error']) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json([
            'message' => 'Étudiant affecté au groupe avec succès',
            'data' => new GroupAssignmentResource($result['assignment']),
        ], 201);
    }

    public function removeAssignment(int $id): JsonResponse
    {
        $result = $this->assignmentService->removeStudent($id);

        if ($result['error']) {
            return response()->json(['message' => $result['error']], 404);
        }

        return response()->json([
            'message' => 'Affectation supprimée avec succès',
        ]);
    }

    public function students(int $id): JsonResponse
    {
        $group = Group::on('tenant')->findOrFail($id);
        $assignments = GroupAssignment::on('tenant')
            ->where('group_id', $id)
            ->with(['student', 'assignedByUser'])
            ->orderBy('assigned_at', 'desc')
            ->get();

        return response()->json([
            'data' => GroupAssignmentResource::collection($assignments),
            'meta' => [
                'total' => $assignments->count(),
                'group' => [
                    'id' => $group->id,
                    'code' => $group->code,
                    'name' => $group->name,
                    'capacity_max' => $group->capacity_max,
                    'available_slots' => $group->available_slots,
                ],
            ],
        ]);
    }

    public function exportStudents(int $id): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $group = Group::on('tenant')->findOrFail($id);
        $filename = 'groupe_'.$group->code.'_'.now()->format('Y-m-d').'.xlsx';

        return (new GroupStudentsExport($group))->download($filename);
    }

    public function statistics(int $id): JsonResponse
    {
        $stats = $this->assignmentService->getGroupStats($id);

        if (empty($stats)) {
            return response()->json(['message' => 'Groupe non trouvé'], 404);
        }

        return response()->json([
            'data' => $stats,
        ]);
    }

    public function moveStudent(Request $request, int $fromGroupId): JsonResponse
    {
        $request->validate([
            'student_id' => ['required', 'integer'],
            'to_group_id' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->assignmentService->moveStudent(
            $request->input('student_id'),
            $fromGroupId,
            $request->input('to_group_id'),
            $request->input('reason')
        );

        if ($result['error']) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json([
            'message' => 'Étudiant transféré avec succès',
            'data' => new GroupAssignmentResource($result['assignment']),
        ]);
    }

    /**
     * Get students not yet assigned to any group for a given module/level/academic year.
     * These are students eligible for group assignment.
     *
     * Students must have an active enrollment (StudentEnrollment) for the given
     * programme, level, and academic year, and must NOT already have a GroupAssignment
     * for this module and academic year.
     */
    public function unassignedStudents(Request $request): JsonResponse
    {
        $request->validate([
            'module_id' => ['required', 'integer'],
            'level' => ['required', 'string'],
            'academic_year_id' => ['required', 'integer'],
            'program_id' => ['nullable', 'integer'],
        ]);

        $moduleId = $request->input('module_id');
        $level = $request->input('level');
        $academicYearId = $request->input('academic_year_id');
        $programId = $request->input('program_id');

        // If program_id not provided, try to get it from an existing group
        if (! $programId) {
            $existingGroup = Group::on('tenant')
                ->where('module_id', $moduleId)
                ->where('level', $level)
                ->where('academic_year_id', $academicYearId)
                ->first();

            if ($existingGroup) {
                $programId = $existingGroup->program_id;
            } else {
                // Try to get from module's programmes
                $module = \Modules\StructureAcademique\Entities\Module::on('tenant')
                    ->with('programmes')
                    ->find($moduleId);

                if ($module && $module->programmes->isNotEmpty()) {
                    $programId = $module->programmes->first()->id;
                } else {
                    return response()->json([
                        'data' => [],
                        'meta' => ['total' => 0, 'message' => 'Aucun programme trouvé pour ce module'],
                    ]);
                }
            }
        }

        // Get student IDs that are already assigned to a group for this module/year
        $assignedStudentIds = GroupAssignment::on('tenant')
            ->where('module_id', $moduleId)
            ->where('academic_year_id', $academicYearId)
            ->pluck('student_id')
            ->toArray();

        // Get students who:
        // 1. Have an active enrollment for this programme/level/academic year
        // 2. Are NOT already assigned to a group for this module
        $enrollments = \Modules\Enrollment\Entities\StudentEnrollment::on('tenant')
            ->with(['student' => function ($query) {
                $query->select('id', 'matricule', 'firstname', 'lastname', 'email', 'status');
            }])
            ->where('programme_id', $programId)
            ->where('level', $level)
            ->where('academic_year_id', $academicYearId)
            ->where('status', 'Actif')
            ->whereNotIn('student_id', $assignedStudentIds)
            ->whereHas('student', function ($query) {
                $query->where('status', 'Actif');
            })
            ->get();

        // Transform to simple student list
        $students = $enrollments->map(function ($enrollment) use ($level) {
            $student = $enrollment->student;

            return [
                'id' => $student->id,
                'matricule' => $student->matricule,
                'firstname' => $student->firstname,
                'lastname' => $student->lastname,
                'email' => $student->email,
                'level' => $level,
                'enrollment_id' => $enrollment->id,
            ];
        })->sortBy('lastname')->values();

        return response()->json([
            'data' => $students,
            'meta' => [
                'total' => $students->count(),
                'module_id' => $moduleId,
                'level' => $level,
                'academic_year_id' => $academicYearId,
                'program_id' => $programId,
            ],
        ]);
    }
}
